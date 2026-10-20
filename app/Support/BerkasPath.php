<?php

namespace App\Support;

use Illuminate\Support\Str;

final class BerkasPath
{
    /**
     * The temporary upload key: epoch milliseconds, an underscore, then a UUID v4.
     */
    public const PATTERN = '/^\d{13}_[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    /**
     * Build a new temporary upload key.
     */
    public static function make(): string
    {
        return now()->getTimestampMs().'_'.Str::uuid()->toString();
    }

    /**
     * Read the creation timestamp embedded in the key.
     */
    public static function epochMillis(string $path): int
    {
        return (int) Str::before($path, '_');
    }

    /**
     * Determine if the key is older than the configured lifetime.
     */
    public static function isExpired(string $path, int $ttlMinutes): bool
    {
        return now()->getTimestampMs() - self::epochMillis($path) > $ttlMinutes * 60_000;
    }
}
