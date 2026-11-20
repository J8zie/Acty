<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Report;
use App\Models\ReportTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportTaskFactory extends Factory
{
    protected $model = ReportTask::class;


    // Makes a fake record for tests.

    public function public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'volunteer_id' => User::factory()->volunteer(),
            'status' => TaskStatus::Accepted,
            'accepted_at' => now(),
        ];
    }
}
