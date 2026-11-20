<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/** The landing page numbers (/api/stats) are cached for a few seconds. One place knows the key. */
final class StatsCache
{
    public const KEY = 'actify.stats';

    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }
}
