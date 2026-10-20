<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class DiklatResource extends JsonResource
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
            'pegawai_uuid' => $this->whenLoaded('pegawai', fn (): string => $this->pegawai->uuid),
            'jenis_pelatihan' => JenisPelatihanResource::make($this->whenLoaded('jenisPelatihan')),
            'nomor_surat' => $this->nomor_surat,
            'materi_pengembangan' => $this->materi_pengembangan,
            'dari_tanggal_pelaksanaan' => $this->dari_tanggal_pelaksanaan->toDateString(),
            'sampai_tanggal_pelaksanaan' => $this->sampai_tanggal_pelaksanaan->toDateString(),
            'jumlah_jam_pelatihan' => $this->jumlah_jam_pelatihan,
            'berkas_url' => $this->berkasUrl(),
        ];
    }

    /**
     * Build a temporary URL for the private attachment stored on S3.
     */
    private function berkasUrl(): ?string
    {
        if (blank($this->berkas_path)) {
            return null;
        }

        return Storage::disk('s3')->temporaryUrl(
            $this->berkas_path,
            now()->addMinutes((int) config('filesystems.disks.s3.temporary_url_ttl', 15)),
        );
    }
}
