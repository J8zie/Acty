<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

// The cached home-page report numbers.
final class StatsCache
{
    // The cache key for the stats.
    public const KEY = 'actify.stats';

    // Throws away the saved stats so they are counted again.
    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }
}
