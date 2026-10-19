<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\HasUuid;
use Database\Factories\DiklatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

#[Fillable([
    'pegawai_id',
    'jenis_pelatihan_id',
    'nomor_surat',
    'materi_pengembangan',
    'dari_tanggal_pelaksanaan',
    'sampai_tanggal_pelaksanaan',
    'jumlah_jam_pelatihan',
    'berkas_path',
    'berkas_filename',
])]
class Diklat extends Model
{
    /** @use HasFactory<DiklatFactory> */
    use HasAuditColumns, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'diklat';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dari_tanggal_pelaksanaan' => 'date',
            'sampai_tanggal_pelaksanaan' => 'date',
            'jumlah_jam_pelatihan' => 'integer',
        ];
    }

    /**
     * Get the pegawai that owns the diklat.
     *
     * @return BelongsTo<Pegawai, $this>
     */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    /**
     * Get the training type of the diklat.
     *
     * @return BelongsTo<JenisPelatihan, $this>
     */
    public function jenisPelatihan(): BelongsTo
    {
        return $this->belongsTo(JenisPelatihan::class);
    }

    /**
     * Scope route binding to the diklat owned by the authenticated pegawai.
     *
     * A record that belongs to another pegawai never resolves, so show, update,
     * and delete answer with a 404 instead of leaking that the record exists.
     *
     * @param  Builder<Diklat>  $query
     * @return Builder<Diklat>
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query, $value, $field)
            ->where('pegawai_id', Auth::user()?->pegawai?->id);
    }
}
