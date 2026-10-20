<?php

namespace App\Services;

use App\Models\BerkasUpload;
use App\Models\User;
use finfo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class BerkasUploadService
{
    private const TEMP_DISK = 's3-temp';

    private const PERMANENT_DISK = 's3';

    private const MAX_BYTES = 10 * 1024 * 1024;

    /**
     * @var array<string, string>
     */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /**
     * Create a pending upload and return the model the presigned URL is built from.
     *
     * @param  array{filename: string}  $data
     */
    public function presign(User $user, array $data): BerkasUpload
    {
        $extension = mb_strtolower(pathinfo($data['filename'], PATHINFO_EXTENSION));

        return BerkasUpload::create([
            'user_id' => $user->id,
            'object_key' => 'upload/'.Str::ulid().'.'.$extension,
            'original_filename' => $data['filename'],
            'upload_url_expires_at' => now()->addMinutes(
                (int) config('filesystems.disks.s3-temp.upload_ttl', 15),
            ),
            'purge_at' => now()->addMinutes(
                (int) config('filesystems.disks.s3-temp.purge_ttl', 1440),
            ),
        ]);
    }

    /**
     * Lock an upload for attaching to another record.
     *
     * This method must be called inside a database transaction so the row lock
     * holds until the consuming write commits.
     */
    public function claim(User $user, string $uuid): BerkasUpload
    {
        $upload = BerkasUpload::query()
            ->where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->where('purge_at', '>', now())
            ->lockForUpdate()
            ->first();

        if (! $upload) {
            throw ValidationException::withMessages([
                'berkas_uuid' => 'The selected file is invalid or has already been used.',
            ]);
        }

        return $upload;
    }

    /**
     * Verify the temporary object and copy it into the permanent bucket.
     *
     * Returns the permanent object key. The temporary object is left in place
     * so the caller can remove it only after the database write commits.
     */
    public function promote(BerkasUpload $upload, string $directory): string
    {
        $tempDisk = Storage::disk(self::TEMP_DISK);

        if (! $tempDisk->exists($upload->object_key)) {
            throw ValidationException::withMessages([
                'berkas_uuid' => 'The file has not been uploaded or has expired.',
            ]);
        }

        $size = (int) $tempDisk->size($upload->object_key);

        if ($size > self::MAX_BYTES) {
            throw ValidationException::withMessages([
                'berkas_uuid' => 'The file may not be greater than 10 MB.',
            ]);
        }

        $mimeType = $this->detectMimeType($tempDisk->readStream($upload->object_key));
        $extension = self::ALLOWED_MIME_TYPES[$mimeType] ?? null;

        if ($extension === null) {
            throw ValidationException::withMessages([
                'berkas_uuid' => 'The file type is not supported.',
            ]);
        }

        $destination = trim($directory, '/').'/'.Str::ulid().'.'.$extension;
        $stream = $tempDisk->readStream($upload->object_key);

        try {
            $written = Storage::disk(self::PERMANENT_DISK)->writeStream($destination, $stream, [
                'ContentType' => $mimeType,
                'ContentDisposition' => 'attachment; filename="'.addslashes($upload->original_filename).'"',
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $written) {
            throw new RuntimeException('Unable to copy the uploaded file to the permanent bucket.');
        }

        try {
            $upload->update([
                'mime_type' => $mimeType,
                'size' => $size,
                'consumed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk(self::PERMANENT_DISK)->delete($destination);

            throw $exception;
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
    public function deleteTemporary(BerkasUpload $upload): void
    {
        Storage::disk(self::TEMP_DISK)->delete($upload->object_key);
    }

    /**
     * Cancel a pending upload.
     */
    public function delete(BerkasUpload $upload): void
    {
        if ($upload->consumed_at !== null) {
            throw ValidationException::withMessages([
                'berkas_uuid' => 'The file has already been used.',
            ]);
        }

        Storage::disk(self::TEMP_DISK)->delete($upload->object_key);

        $upload->delete();
    }

    /**
     * Sniff the file type from its contents instead of trusting the client header.
     */
    private function detectMimeType(mixed $stream): string
    {
        if (! is_resource($stream)) {
            throw ValidationException::withMessages([
                'berkas_uuid' => 'The file has not been uploaded or has expired.',
            ]);
        }

        $prefix = fread($stream, 8192);
        fclose($stream);

        return (new finfo(FILEINFO_MIME_TYPE))->buffer($prefix ?: '') ?: 'application/octet-stream';
    }
}
