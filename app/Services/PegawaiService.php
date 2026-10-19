<?php

namespace App\Services;

use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PegawaiService
{
    /**
     * @param  array{search?: string|null, per_page?: int|null}  $filters
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $search = $filters['search'] ?? null;

        return Pegawai::query()
            ->with('user')
            ->when($search, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('nip', 'like', "{$search}%")
                        ->orWhereRaw('lower(nama) like ?', [mb_strtolower($search).'%'])
                        ->orWhere('jabatan', 'ilike', "%{$search}%")
                        ->orWhere('bidang', 'ilike', "%{$search}%")
                        ->orWhereHas('user', function (Builder $query) use ($search): void {
                            $query->whereRaw('lower(email) like ?', ['%'.mb_strtolower($search).'%']);
                        });
                });
            })
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * @param  array{email?: string|null, password: string, nip: string, nama: string, tanggal_lahir?: string|null, jabatan?: string|null, bidang?: string|null, gender?: string|null, tmt?: string|null}  $data
     */
    public function create(array $data): Pegawai
    {
        return DB::transaction(function () use ($data): Pegawai {
            $user = User::create([
                'email' => $data['email'] ?? null,
                'username' => $data['nip'],
                'password' => $data['password'],
            ]);

            $pegawai = $user->pegawai()->create($this->pegawaiAttributes($data));

            return $pegawai->load('user');
        });
    }

    public function show(Pegawai $pegawai): Pegawai
    {
        return $pegawai->load('user');
    }

    /**
     * @param  array{email: string|null, password?: string|null, nip: string, nama: string, tanggal_lahir: string|null, jabatan: string|null, bidang: string|null, gender: string|null, tmt: string|null}  $data
     */
    public function update(Pegawai $pegawai, array $data): Pegawai
    {
        return DB::transaction(function () use ($pegawai, $data): Pegawai {
            $userData = [
                'email' => $data['email'],
                'username' => $data['nip'],
            ];

            if (filled($data['password'] ?? null)) {
                $userData['password'] = $data['password'];
            }

            $pegawai->user->update($userData);
            $pegawai->update($this->pegawaiAttributes($data));

            return $pegawai->refresh()->load('user');
        });
    }

    /**
     * Map the request payload to pegawai columns, storing gender as the is_male flag.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function pegawaiAttributes(array $data): array
    {
        return [
            ...Arr::except($data, ['email', 'password', 'gender']),
            'is_male' => match ($data['gender'] ?? null) {
                'male' => true,
                'female' => false,
                default => null,
            },
        ];
    }

    public function delete(Pegawai $pegawai): void
    {
        DB::transaction(function () use ($pegawai): void {
            $pegawai->delete();
            $pegawai->user->delete();
        });
    }
}
