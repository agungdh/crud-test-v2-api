<?php

namespace App\Services;

use App\Support\BerkasPath;
use Aws\S3\PostObjectV4;
use finfo;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BerkasUploadService
{
    private const TEMP_DISK = 's3-temp';

    private const PERMANENT_DISK = 's3';

    /**
     * @var array<string, string>
     */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /**
     * Create a presigned POST for an empty temporary object.
     *
     * The requested maximum size is embedded in the S3 policy as a
     * content-length-range condition, so S3 rejects an oversized upload.
     *
     * @param  array{max_size: int}  $data
     * @return array{path: string, upload_url: string, fields: array<string, string>, expires_at: string, max_size: int}
     */
    public function presign(array $data): array
    {
        $path = BerkasPath::make();
        $expiresAt = now()->addMinutes(
            (int) config('filesystems.disks.s3-temp.upload_ttl', 15),
        );
        $bucket = (string) config('filesystems.disks.s3-temp.bucket');
        $maxSize = (int) $data['max_size'];

        /** @var AwsS3V3Adapter $disk */
        $disk = Storage::disk(self::TEMP_DISK);

        $post = new PostObjectV4(
            $disk->getClient(),
            $bucket,
            ['key' => $path],
            [
                ['bucket' => $bucket],
                ['eq', '$key', $path],
                ['content-length-range', 1, $maxSize],
            ],
            $expiresAt,
        );

        return [
            'path' => $path,
            'upload_url' => $post->getFormAttributes()['action'],
            'fields' => $post->getFormInputs(),
            'expires_at' => $expiresAt->toIso8601ZuluString(),
            'max_size' => $maxSize,
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
}
