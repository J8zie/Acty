<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{

    // Creates the table.

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tablesInCurrentSchema() as $table) {
            DB::statement($this->alterTableStatement($table, 'ENABLE'));
        }
    }


    // Drops the table.

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tablesInCurrentSchema() as $table) {
            DB::statement($this->alterTableStatement($table, 'DISABLE'));
        }
    }


    private function tablesInCurrentSchema(): array
    {
        return DB::select(
            'select schemaname, tablename from pg_tables where schemaname = current_schema()'
        );
    }

    private function alterTableStatement(object $table, string $action): string
    {
        $schema = str_replace('"', '""', $table->schemaname);
        $name = str_replace('"', '""', $table->tablename);

        return sprintf('ALTER TABLE "%s"."%s" %s ROW LEVEL SECURITY', $schema, $name, $action);
    }
};
