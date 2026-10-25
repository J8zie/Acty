<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create("task",function(Blueprint $table){
            $table->uuid("id")->primary();
            $table->foreignUuid("report_id")->constrained("reports")->cascadeOnDelete();
            $table->foreignUuid("volunteer_id")->constrained("users")->cascadeOnDelete();
            $table->enum("task_status",["complete","in_progress","accepted"]);
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task');

    }
};
