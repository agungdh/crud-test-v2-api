<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasUuid
{
    /**
     * Assign a UUID v4 to the model before it is persisted.
     */
    protected static function bootHasUuid(): void
    {
        static::creating(function (self $model): void {
            if (blank($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the route key for the model.
     *
     * The public identifier is the UUID, while the database keeps using the
     * auto-incrementing primary key internally.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
