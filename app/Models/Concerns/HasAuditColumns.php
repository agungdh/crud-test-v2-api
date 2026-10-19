<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Auth;

trait HasAuditColumns
{
    /**
     * Hide the audit columns from array and JSON serialization.
     *
     * Audit columns belong to the database only and must never reach the API.
     */
    public function initializeHasAuditColumns(): void
    {
        $this->mergeHidden([
            'created_by',
            'updated_by',
            'deleted_by',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
    }

    /**
     * Keep the audit columns in sync with the authenticated actor.
     */
    protected static function bootHasAuditColumns(): void
    {
        static::creating(function (self $model): void {
            $actorId = Auth::id();

            $model->created_by ??= $actorId;
            $model->updated_by ??= $actorId;
        });

        static::updating(function (self $model): void {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });

        static::deleting(function (self $model): void {
            if (! method_exists($model, 'isForceDeleting') || $model->isForceDeleting()) {
                return;
            }

            $attributes = ['deleted_by' => Auth::id()];

            if (Auth::check()) {
                $attributes['updated_by'] = Auth::id();
            }

            $model->forceFill($attributes)->saveQuietly();
        });

        static::restoring(function (self $model): void {
            $model->deleted_by = null;
        });
    }
}
