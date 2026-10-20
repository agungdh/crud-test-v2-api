<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use App\Models\Concerns\HasUuid;
use Database\Factories\BerkasUploadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

#[Fillable([
    'user_id',
    'object_key',
    'original_filename',
    'mime_type',
    'size',
    'upload_url_expires_at',
    'purge_at',
    'consumed_at',
])]
class BerkasUpload extends Model
{
    /** @use HasFactory<BerkasUploadFactory> */
    use HasAuditColumns, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'berkas_upload';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'upload_url_expires_at' => 'datetime',
            'purge_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the upload.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope route binding to the upload owned by the authenticated user.
     *
     * @param  Builder<BerkasUpload>  $query
     * @return Builder<BerkasUpload>
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query, $value, $field)
            ->where('user_id', Auth::id());
    }
}
