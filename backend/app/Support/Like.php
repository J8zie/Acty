<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

// Helpers for safe "contains this text" database searches.
final class Like
{

    // Wraps a word in % wildcards and escapes special characters.
    public static function contains(string $word): string
    {
        return '%'.str_replace(['|', '%', '_'], ['||', '|%', '|_'], $word).'%';
    }


    // Builds a case-insensitive LIKE clause for the current database.
    public static function sql(string $column): string
    {
        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        return "{$column} {$operator} ? ESCAPE '|'";
    }
}
