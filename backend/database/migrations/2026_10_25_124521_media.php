<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
    Schema::create('media',function(Blueprint $table){
        $table->uuid("id")->primary();
        $table->foreignUudi("report_id")->constrained("reports")->cascadeOnDelete();
        $table->foreignUuid("task_id")->constrained("task")->cascadeOnDelete();
        $table->string("media_url");
        $table->enum("mediatype",['photo','video']);
        $table->enum("mediakind",["report","outcome"]);
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
