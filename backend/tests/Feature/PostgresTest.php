<?php

// Tests for Postgres Test.

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgresTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Needs PostgreSQL: run with DB_CONNECTION=pgsql (see docs/SUPABASE-AND-CLOUDINARY.md).');
        }
    }

    public function test_every_table_has_row_level_security_switched_on(): void
    {
        $without = DB::select('select tablename from pg_tables where schemaname = current_schema() and not rowsecurity');

        $this->assertSame([], array_map(fn ($t) => $t->tablename, $without), 'These tables could be read through Supabase\'s public API.');
    }

    public function test_searching_ignores_upper_and_lower_case(): void
    {
        $category = $this->category();
        \App\Models\Report::factory()->create(['category_id' => $category->id, 'title' => 'Large Pothole on Rizal']);

        $this->getJson('/api/reports?q=POTHOLE')->assertOk()->assertJsonCount(1);
        $this->getJson('/api/reports?q=rizal')->assertOk()->assertJsonCount(1);
    }

    public function test_a_percent_sign_is_searched_literally(): void
    {
        $category = $this->category();
        \App\Models\Report::factory()->create(['category_id' => $category->id, 'title' => '100% broken']);
        \App\Models\Report::factory()->create(['category_id' => $category->id, 'title' => 'Fine report']);

        $this->getJson('/api/reports?q=100%25')->assertOk()->assertJsonCount(1);
    }
}
