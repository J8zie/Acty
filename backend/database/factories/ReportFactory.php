<?php

namespace Database\Factories;

use App\Enums\AssistanceType;
use App\Enums\ReportSource;
use App\Enums\ReportStatus;
use App\Enums\ReviewStatus;
use App\Enums\TaskStatus;
use App\Enums\Urgency;
use App\Models\Category;
use App\Models\GuestReporter;
use App\Models\Report;
use App\Models\ReportTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReportFactory extends Factory
{
    protected $model = Report::class;


    // Makes a fake record for tests.

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'assistance_type' => fake()->randomElement(AssistanceType::cases()),
            'urgency' => fake()->randomElement(Urgency::cases()),
            'status' => ReportStatus::Open,
            'barangay' => 'Apokon',
            'latitude' => 7.4631,
            'longitude' => 125.8112,
            'source' => ReportSource::Account,
            'review_status' => ReviewStatus::Approved,
        ];
    }


    public function guestPending(): static
    {
        return $this->state([
            'user_id' => null,
            'source' => ReportSource::Guest,
            'review_status' => ReviewStatus::Pending,
            'image_disk' => 'private',
            'reference' => 'ACT-'.fake()->unique()->numerify('######'),
        ])->afterCreating(function (Report $report) {
            GuestReporter::create([
                'report_id' => $report->id,
                'first_name' => 'Gina',
                'last_name' => 'Guest',
                'phone' => '0912 345 6789',
                'phone_key' => '9123456789',
                'email' => 'gina@example.test',
                'barangay' => 'Magugpo Poblacion',
                'selfie_path' => 'selfies/'.fake()->uuid().'.jpg',
                'liveness_prompt' => 'Hold up 2 fingers',
                'photo_captured_at' => now()->subMinute(),
                'selfie_captured_at' => now()->subMinutes(2),
                'photo_capture_method' => 'live',
                'selfie_capture_method' => 'live',
                'ip' => '203.0.113.9',
                'consented_at' => now(),
            ]);
        });
    }

    public function guestApproved(): static
    {
        return $this->guestPending()->state(['review_status' => ReviewStatus::Approved, 'image_disk' => 'public']);
    }

    public function guestRejected(): static
    {
        return $this->guestPending()->state(['review_status' => ReviewStatus::Rejected]);
    }


    public function withTask(TaskStatus $task = TaskStatus::Accepted, ?User $volunteer = null, ?int $rating = null): static
    {
        return $this->afterCreating(function (Report $report) use ($task, $volunteer, $rating) {
            $report->status = $task === TaskStatus::Complete ? ReportStatus::Resolved : ReportStatus::InProgress;
            $report->save();

            ReportTask::create([
                'report_id' => $report->id,
                'volunteer_id' => ($volunteer ?? User::factory()->volunteer()->create())->id,
                'status' => $task,
                'accepted_at' => now()->subDays(2),
                'started_at' => $task === TaskStatus::Accepted ? null : now()->subDay(),
                'completed_at' => $task === TaskStatus::Complete ? now() : null,
                'outcome_note' => $task === TaskStatus::Complete ? 'Cleared and cleaned.' : null,
                'rating' => $rating,
                'rated_at' => $rating ? now() : null,
            ]);
        });
    }
}
