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
        Schema::create('reports',function(Blueprint $table){
            $table->uuid("id")->primary();
            $table->foreignUuid("user_id")->constrained("users")->cascadeOnDelete();
            $table->foreignUuid("category_id")->constrained("category")->cascadeOnDelete();
            $table->string("full_name");
            $table->string("photo_url");
            $table->string("title");
            $table->string("description");
            $table->enum("assistance_type",["cleanup","repairs","supplies","manpower","awareness","others"])->default;
            $table->decimal("long");
            $table->decimal("lan");
            $table->enum("status",["resolve","in_progress","open","cancelled"]);
            $table->enum("urgency",["low","medium","high"])->default("medium");
            $table->timestamp("created_at");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
