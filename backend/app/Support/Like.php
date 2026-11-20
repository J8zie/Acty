<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * One place that knows how to write a "contains this word, ignoring upper/lower case" search.
 *
 * SQLite and MySQL ignore case in LIKE, but PostgreSQL (for example Supabase) does not: there the same search needs
 * ILIKE. Everything else in the queries is the same on all three databases.
 */
final class Like
{
    /** "%word%" where a % or _ typed by the person is searched literally ("|" is the escape character). */
    public static function contains(string $word): string
    {
        return '%'.str_replace(['|', '%', '_'], ['||', '|%', '|_'], $word).'%';
    }

    /** SQL for one column: `reports.title LIKE ? ESCAPE '|'` (or ILIKE on PostgreSQL). Bind the value from contains(). */
    public static function sql(string $column): string
    {
        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        return "{$column} {$operator} ? ESCAPE '|'";
    }
}
