<?php

namespace App\Services;

use App\Models\JenisPelatihan;
use App\Support\CursorPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class JenisPelatihanService
{
    /**
     * @param  array{search?: string|null, cursor?: string|null}  $filters
     */
    public function paginate(array $filters): CursorPage
    {
        $search = $filters['search'] ?? null;

        $query = JenisPelatihan::query()
            ->when($search, function (Builder $query, string $search): void {
                $query->whereRaw('lower(jenis_pelatihan) like ?', [mb_strtolower($search).'%']);
            });

        return CursorPage::fromQuery($query, $filters['cursor'] ?? null);
    }

    /**
     * @param  array{jenis_pelatihan: string}  $data
     */
    public function create(array $data): JenisPelatihan
    {
        return JenisPelatihan::create($data);
    }

    /**
     * @param  array{jenis_pelatihan: string}  $data
     */
    public function update(JenisPelatihan $jenisPelatihan, array $data): JenisPelatihan
    {
        $jenisPelatihan->update($data);

        return $jenisPelatihan->refresh();
    }

    /**
     * Soft delete the training type, unless it is still referenced by a diklat.
     *
     * @throws ValidationException
     */
    public function delete(JenisPelatihan $jenisPelatihan): void
    {
        if ($jenisPelatihan->diklat()->exists()) {
            throw ValidationException::withMessages([
                'jenis_pelatihan' => ['Jenis pelatihan masih dipakai oleh data diklat.'],
            ]);
        }

        $jenisPelatihan->delete();
    }
}
