<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BerkasUploadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $presigned = Storage::disk('s3-temp')->temporaryUploadUrl(
            $this->object_key,
            $this->upload_url_expires_at,
            ['IfNoneMatch' => '*'],
        );

        return [
            'uuid' => $this->uuid,
            'filename' => $this->original_filename,
            'upload_url' => $presigned['url'],
            'headers' => $this->headers($presigned['headers']),
            'expires_at' => $this->upload_url_expires_at->toIso8601ZuluString(),
        ];
    }

    /**
     * Normalize the signed headers for the client.
     *
     * @param  array<string, array<int, string>>  $headers
     * @return array<string, string>
     */
    private function headers(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $values) {
            if (mb_strtolower($name) === 'host') {
                continue;
            }

            $normalized[$name] = implode(', ', $values);
        }

        return $normalized;
    }
}
