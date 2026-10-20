<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BerkasUploadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'path' => $this->resource['path'],
            'upload_url' => $this->resource['upload_url'],
            'headers' => $this->headers($this->resource['headers']),
            'expires_at' => $this->resource['expires_at'],
            'max_size' => $this->resource['max_size'],
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
            if (in_array(mb_strtolower($name), ['host', 'content-length'], true)) {
                continue;
            }

            $normalized[$name] = implode(', ', $values);
        }

        return $normalized;
    }
}
