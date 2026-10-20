<?php

namespace App\Services;

use App\Support\BerkasPath;
use finfo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BerkasUploadService
{
    private const TEMP_DISK = 's3-temp';

    private const PERMANENT_DISK = 's3';

    private const DEFAULT_MAX_BYTES = 10 * 1024 * 1024;

    /**
     * @var array<string, string>
     */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /**
     * Create a presigned upload for an empty temporary object.
     *
     * The declared size is signed into the URL as Content-Length, so the
     * storage server rejects an upload whose body does not match it.
     *
     * @param  array{size: int}  $data
     * @return array{path: string, upload_url: string, headers: array<string, array<int, string>>, expires_at: string, max_size: int}
     */
    public function presign(array $data): array
    {
        $path = BerkasPath::make();
        $expiresAt = now()->addMinutes(
            (int) config('filesystems.disks.s3-temp.upload_ttl', 15),
        );

        $presigned = Storage::disk(self::TEMP_DISK)->temporaryUploadUrl(
            $path,
            $expiresAt,
            [
                'IfNoneMatch' => '*',
                'ContentLength' => (int) $data['size'],
            ],
        );

        return [
            'path' => $path,
            'upload_url' => $presigned['url'],
            'headers' => $presigned['headers'],
            'expires_at' => $expiresAt->toIso8601ZuluString(),
            'max_size' => $this->maxBytes(),
        ];
    }

    /**
     * Verify a temporary object and copy it into the permanent bucket.
     *
     * Returns the permanent object key. The temporary object is left in place
     * so the caller can remove it only after the database write commits.
     */
    public function promote(string $temporaryPath, string $module): string
    {
        $tempDisk = Storage::disk(self::TEMP_DISK);
        $purgeTtl = (int) config('filesystems.disks.s3-temp.purge_ttl', 1440);

        if (BerkasPath::isExpired($temporaryPath, $purgeTtl) || ! $tempDisk->exists($temporaryPath)) {
            throw ValidationException::withMessages([
                'berkas_path' => 'The file has not been uploaded or has expired.',
            ]);
        }

        $size = (int) $tempDisk->size($temporaryPath);

        if ($size > $this->maxBytes()) {
            throw ValidationException::withMessages([
                'berkas_path' => 'The file may not be greater than '.$this->maxMegabytes().' MB.',
            ]);
        }

        $mimeType = $this->detectMimeType($tempDisk->readStream($temporaryPath));
        $extension = self::ALLOWED_MIME_TYPES[$mimeType] ?? null;

        if ($extension === null) {
            throw ValidationException::withMessages([
                'berkas_path' => 'The file type is not supported.',
            ]);
        }

        $filename = Str::uuid()->toString();
        $destination = trim($module, '/').'/'.$filename;
        $stream = $tempDisk->readStream($temporaryPath);

        try {
            $written = Storage::disk(self::PERMANENT_DISK)->writeStream($destination, $stream, [
                'ContentType' => $mimeType,
                'ContentDisposition' => 'attachment; filename="'.$filename.'.'.$extension.'"',
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $written) {
            throw new RuntimeException('Unable to copy the uploaded file to the permanent bucket.');
        }

        return $destination;
    }

    /**
     * Delete a promoted object when the surrounding database transaction fails.
     */
    public function discard(string $path): void
    {
        Storage::disk(self::PERMANENT_DISK)->delete($path);
    }

    /**
     * Delete a temporary object after the consuming transaction commits.
     */
    public function deleteTemporary(string $path): void
    {
        Storage::disk(self::TEMP_DISK)->delete($path);
    }

    /**
     * Sniff the file type from its contents instead of trusting the client header.
     */
    private function detectMimeType(mixed $stream): string
    {
        if (! is_resource($stream)) {
            throw ValidationException::withMessages([
                'berkas_path' => 'The file has not been uploaded or has expired.',
            ]);
        }

        $prefix = fread($stream, 8192);
        fclose($stream);

        return (new finfo(FILEINFO_MIME_TYPE))->buffer($prefix ?: '') ?: 'application/octet-stream';
    }

    private function maxBytes(): int
    {
        return (int) config('filesystems.disks.s3-temp.max_bytes', self::DEFAULT_MAX_BYTES);
    }

    private function maxMegabytes(): int|float
    {
        $megabytes = $this->maxBytes() / (1024 * 1024);

        return fmod($megabytes, 1.0) === 0.0 ? (int) $megabytes : round($megabytes, 2);
    }
}
