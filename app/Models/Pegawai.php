<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\HasUuid;
use Database\Factories\PegawaiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'nip', 'nama', 'tanggal_lahir', 'jabatan', 'bidang', 'is_male', 'tmt'])]
class Pegawai extends Model
{
    /** @use HasFactory<PegawaiFactory> */
    use HasAuditColumns, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'pegawai';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'is_male' => 'boolean',
            'tmt' => 'date',
        ];
    }

    /**
     * Get the user account linked to the pegawai.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
