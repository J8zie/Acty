<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    // Creates the table.

    public function up(): void
    {
        Schema::create('guest_reporters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('first_name', 60);
            $table->string('last_name', 60);
            $table->string('phone', 30);
            $table->string('phone_key', 10);
            $table->string('email')->nullable();
            $table->string('barangay', 80);
            $table->string('selfie_path');
            $table->string('liveness_prompt', 120);
            $table->timestamp('photo_captured_at');
            $table->timestamp('selfie_captured_at');
            $table->string('photo_capture_method', 20);
            $table->string('selfie_capture_method', 20);
            $table->string('ip', 45)->nullable();
            $table->timestamp('consented_at');
            $table->timestamps();

            $table->index(['ip', 'created_at']);
            $table->index(['phone_key', 'created_at']);
        });
    }


    // Drops the table.

    public function down(): void
    {
        Schema::dropIfExists('guest_reporters');
    }
};
