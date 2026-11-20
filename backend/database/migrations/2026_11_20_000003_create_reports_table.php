<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('title', 120);
            $table->text('description');
            $table->string('assistance_type', 24);
            $table->string('urgency', 16);
            $table->string('status', 20)->default('open');
            $table->string('barangay', 80);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('image_path')->nullable();
            $table->string('image_disk', 32)->default('public');
            $table->string('source', 16)->default('account');
            $table->string('review_status', 16)->default('approved');
            $table->string('reference', 20)->nullable()->unique();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'id']);
            $table->index(['review_status', 'source', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('category_id');
            $table->index('reviewed_by');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
