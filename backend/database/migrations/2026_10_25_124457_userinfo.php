<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    Schema::create('userinfo',function(Blueprint $table){
        $table->uuid("id")->primary();
        $table->foreignUuid("user_id")->constrained('users')->cascadeOnDelete();
        $table->foreignUuid("category_id")->constrained('category');
        $table->string("First_Name");
        $table->string("Last_Name");
        $table->string("Email")->unique();
        $table->string("photo_url");
        $table->timestamp("created_at");
    });
 
    }

    public function down(): void
    {
        Schema::dropIfExists('usersinfo');

    }
};
