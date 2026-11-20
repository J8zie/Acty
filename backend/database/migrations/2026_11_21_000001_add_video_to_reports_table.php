<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adds optional video columns to the reports table.
return new class extends Migration
{
    // Adds the video path and disk columns.
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('video_path')->nullable()->after('image_disk');
            $table->string('video_disk')->nullable()->after('video_path');
        });
    }

    // Removes the video columns.
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['video_path', 'video_disk']);
        });
    }
};
