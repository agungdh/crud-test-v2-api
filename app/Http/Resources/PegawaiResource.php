<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PegawaiResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'nip' => $this->nip,
            'nama' => $this->nama,
            'tanggal_lahir' => $this->tanggal_lahir?->toDateString(),
            'jabatan' => $this->jabatan,
            'bidang' => $this->bidang,
            'is_male' => $this->is_male,
            'tmt' => $this->tmt?->toDateString(),
            'user_uuid' => $this->whenLoaded('user', fn (): string => $this->user->uuid),
            'user' => UserResource::make($this->whenLoaded('user')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
