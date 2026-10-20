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
            'fields' => $this->resource['fields'],
            'expires_at' => $this->resource['expires_at'],
            'max_size' => $this->resource['max_size'],
        ];
    }
}
