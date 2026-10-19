<?php

namespace App\Services;

use App\Models\Diklat;
use App\Models\JenisPelatihan;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class DiklatService
{
    private const BERKAS_DISK = 's3';

    /**
     * @param  array{search?: string|null, jenis_pelatihan_uuid?: string|null, dari_tanggal?: string|null, sampai_tanggal?: string|null, per_page?: int|null}  $filters
     */
    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        $pegawai = $this->pegawaiOf($user);
        $search = $filters['search'] ?? null;

        return Diklat::query()
            ->with(['pegawai', 'jenisPelatihan'])
            ->where('pegawai_id', $pegawai->id)
            ->when($search, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereRaw('lower(nomor_surat) like ?', [mb_strtolower($search).'%'])
                        ->orWhere('materi_pengembangan', 'ilike', "%{$search}%");
                });
            })
            ->when($filters['jenis_pelatihan_uuid'] ?? null, function (Builder $query, string $uuid): void {
                $query->whereHas('jenisPelatihan', function (Builder $query) use ($uuid): void {
                    $query->where('uuid', $uuid);
                });
            })
            ->when($filters['dari_tanggal'] ?? null, function (Builder $query, string $date): void {
                $query->where('sampai_tanggal_pelaksanaan', '>=', $date);
            })
            ->when($filters['sampai_tanggal'] ?? null, function (Builder $query, string $date): void {
                $query->where('dari_tanggal_pelaksanaan', '<=', $date);
            })
            ->orderByDesc('dari_tanggal_pelaksanaan')
            ->orderByDesc('id')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * @param  array{jenis_pelatihan_uuid: string, nomor_surat: string, materi_pengembangan: string, dari_tanggal_pelaksanaan: string, sampai_tanggal_pelaksanaan: string, jumlah_jam_pelatihan: int, berkas?: UploadedFile|null}  $data
     */
    public function create(User $user, array $data): Diklat
    {
        $pegawai = $this->pegawaiOf($user);

        return DB::transaction(function () use ($pegawai, $data): Diklat {
            $diklat = $pegawai->diklat()->create([
                ...Arr::except($data, ['jenis_pelatihan_uuid', 'berkas']),
                'jenis_pelatihan_id' => $this->jenisPelatihanId($data['jenis_pelatihan_uuid']),
            ]);

            $berkas = $data['berkas'] ?? null;

            if ($berkas instanceof UploadedFile) {
                $diklat->update([
                    'berkas_path' => $this->storeBerkas($diklat, $berkas),
                    'berkas_filename' => $berkas->getClientOriginalName(),
                ]);
            }

            return $diklat->load(['pegawai', 'jenisPelatihan']);
        });
    }

    public function show(User $user, Diklat $diklat): Diklat
    {
        $this->ensureOwnedBy($user, $diklat);

        return $diklat->load(['pegawai', 'jenisPelatihan']);
    }

    /**
     * @param  array{jenis_pelatihan_uuid: string, nomor_surat: string, materi_pengembangan: string, dari_tanggal_pelaksanaan: string, sampai_tanggal_pelaksanaan: string, jumlah_jam_pelatihan: int, berkas?: UploadedFile|null}  $data
     */
    public function update(User $user, Diklat $diklat, array $data): Diklat
    {
        $this->ensureOwnedBy($user, $diklat);

        $replacedPath = null;
        $uploadedPath = null;

        try {
            DB::transaction(function () use ($diklat, $data, &$replacedPath, &$uploadedPath): void {
                $attributes = [
                    ...Arr::except($data, ['jenis_pelatihan_uuid', 'berkas']),
                    'jenis_pelatihan_id' => $this->jenisPelatihanId($data['jenis_pelatihan_uuid']),
                ];

                if (array_key_exists('berkas', $data)) {
                    $replacedPath = $diklat->berkas_path;
                    $berkas = $data['berkas'];

                    if ($berkas instanceof UploadedFile) {
                        $uploadedPath = $this->storeBerkas($diklat, $berkas);

                        $attributes['berkas_path'] = $uploadedPath;
                        $attributes['berkas_filename'] = $berkas->getClientOriginalName();
                    } else {
                        $attributes['berkas_path'] = null;
                        $attributes['berkas_filename'] = null;
                    }
                }

                $diklat->update($attributes);
            });
        } catch (Throwable $exception) {
            if (filled($uploadedPath)) {
                Storage::disk(self::BERKAS_DISK)->delete($uploadedPath);
            }

            throw $exception;
        }

        if (filled($replacedPath)) {
            Storage::disk(self::BERKAS_DISK)->delete($replacedPath);
        }

        return $diklat->refresh()->load(['pegawai', 'jenisPelatihan']);
    }

    public function delete(User $user, Diklat $diklat): void
    {
        $this->ensureOwnedBy($user, $diklat);

        $berkasPath = $diklat->berkas_path;

        DB::transaction(function () use ($diklat): void {
            $diklat->delete();
        });

        if (filled($berkasPath)) {
            Storage::disk(self::BERKAS_DISK)->delete($berkasPath);
        }
    }

    /**
     * Resolve the authenticated user's pegawai profile.
     */
    private function pegawaiOf(User $user): Pegawai
    {
        return $user->pegawai()->firstOrFail();
    }

    /**
     * Make sure the diklat belongs to the given user, hiding foreign records behind a 404.
     *
     * @throws ModelNotFoundException
     */
    private function ensureOwnedBy(User $user, Diklat $diklat): void
    {
        if ((int) $diklat->pegawai_id !== (int) $this->pegawaiOf($user)->id) {
            throw (new ModelNotFoundException)->setModel(Diklat::class, [$diklat->uuid]);
        }
    }

    /**
     * Resolve the public jenis pelatihan uuid to its numeric foreign key.
     */
    private function jenisPelatihanId(string $uuid): int
    {
        return JenisPelatihan::query()->where('uuid', $uuid)->firstOrFail()->id;
    }

    /**
     * Store the uploaded attachment on S3 and return its object key.
     */
    private function storeBerkas(Diklat $diklat, UploadedFile $berkas): string
    {
        return $berkas->storeAs(
            "diklat/{$diklat->uuid}",
            Str::ulid().'.'.$berkas->extension(),
            self::BERKAS_DISK,
        );
    }
}
