<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\HasUuid;
use Database\Factories\JenisPelatihanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['jenis_pelatihan'])]
class JenisPelatihan extends Model
{
    /** @use HasFactory<JenisPelatihanFactory> */
    use HasAuditColumns, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'jenis_pelatihan';

    /**
     * Get the diklat records that use this training type.
     *
     * @return HasMany<Diklat, $this>
     */
    public function diklat(): HasMany
    {
        return $this->hasMany(Diklat::class);
    }
}
