<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adds an optional outcome video column to volunteer tasks.
return new class extends Migration
{
    // Adds the video path and disk columns.
    public function up(): void
    {
        Schema::table('report_tasks', function (Blueprint $table) {
            $table->string('outcome_video_path')->nullable()->after('outcome_image_path');
            $table->string('outcome_video_disk')->nullable()->after('outcome_video_path');
        });
    }

    // Removes the video columns.
    public function down(): void
    {
        Schema::table('report_tasks', function (Blueprint $table) {
            $table->dropColumn(['outcome_video_path', 'outcome_video_disk']);
        });
    }
};
