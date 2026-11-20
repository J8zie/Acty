<?php

namespace Database\Seeders;

use App\Enums\AssistanceType;
use App\Enums\ReportSource;
use App\Enums\ReportStatus;
use App\Enums\ReviewStatus;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Enums\Urgency;
use App\Models\Category;
use App\Models\Report;
use App\Models\ReportTask;
use App\Models\User;
use Illuminate\Database\Seeder;

class LocalDemoSeeder extends Seeder
{

    // Fills the table with starter data.

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('LocalDemoSeeder never runs in production.');

            return;
        }

        $this->call(CategorySeeder::class);

        $people = [
            ['admin', 'Ada', 'Admin', Role::Admin],
            ['maria', 'Maria', 'Santos', Role::Resident],
            ['juan', 'Juan', 'Dela Cruz', Role::Volunteer],
            ['ana', 'Ana', 'Reyes', Role::Volunteer],
        ];
        $users = [];
        foreach ($people as [$username, $first, $last, $role]) {
            $users[$username] = User::query()->updateOrCreate(['username' => $username], [
                'first_name' => $first, 'last_name' => $last, 'email' => "{$username}@example.test",
                'password' => 'pass1234', 'role' => $role,
            ]);
        }

        $spots = [
            ['Apokon', 7.4631, 125.8112], ['Magugpo Poblacion', 7.4478, 125.8078], ['Visayan Village', 7.4402, 125.8191],
            ['Mankilam', 7.4389, 125.7961], ['San Miguel', 7.4521, 125.8004], ['La Filipina', 7.4713, 125.8231],
            ['Pandapan', 7.4301, 125.8302], ['Madaum', 7.4122, 125.8057], ['Canocotan', 7.4566, 125.8199],
            ['Busaon', 7.4672, 125.7922], ['Magugpo East', 7.4492, 125.8141], ['Nueva Fuerza', 7.4244, 125.7988],
        ];
        $titles = [
            'Overflowing garbage at the market corner', 'Knee-deep flooding after rain', 'Large pothole on the highway',
            'Street light out for two weeks', 'Clogged drainage on Rizal Street', 'Fallen branch blocking the sidewalk',
            'Leaking water pipe near the school', 'Broken benches at the barangay park', 'Faded pedestrian crossing',
            'Uncollected waste behind the chapel', 'Standing water breeding mosquitoes', 'Missing manhole cover',
        ];
        $statuses = ['open', 'open', 'open', 'in_progress', 'open', 'resolved', 'open', 'in_progress', 'resolved', 'open', 'resolved', 'cancelled'];
        $types = AssistanceType::cases();
        $urgencies = [Urgency::Low, Urgency::Medium, Urgency::High];
        $categoryIds = Category::query()->orderBy('id')->pluck('id')->all();

        foreach ($titles as $i => $title) {
            $at = now()->subDays(30 - $i * 2);
            $report = new Report([
                'user_id' => $users['maria']->id,
                'category_id' => $categoryIds[$i % count($categoryIds)],
                'title' => $title,
                'description' => 'It has been like this for a while and neighbours walk past it every day. Please send some help soon.',
                'assistance_type' => $types[$i % count($types)],
                'urgency' => $urgencies[$i % 3],
                'status' => ReportStatus::from($statuses[$i]),
                'barangay' => $spots[$i][0],
                'latitude' => $spots[$i][1],
                'longitude' => $spots[$i][2],
                'source' => ReportSource::Account,
                'review_status' => ReviewStatus::Approved,
            ]);
            $report->created_at = $at;
            $report->updated_at = $at;
            $report->save();

            if ($statuses[$i] === 'in_progress') {
                ReportTask::create([
                    'report_id' => $report->id, 'volunteer_id' => $users['juan']->id,
                    'status' => TaskStatus::Accepted, 'accepted_at' => now()->subDays(2),
                ]);
            }

            if ($statuses[$i] === 'resolved') {
                $rated = $i !== 8;
                ReportTask::create([
                    'report_id' => $report->id,
                    'volunteer_id' => ($i % 2 === 1 ? $users['juan'] : $users['ana'])->id,
                    'status' => TaskStatus::Complete,
                    'accepted_at' => now()->subDays(9),
                    'started_at' => now()->subDays(8),
                    'completed_at' => now()->subDays(5),
                    'outcome_note' => 'Cleared and cleaned. Neighbours helped carry the waste.',
                    'rating' => $rated ? ($i % 2 === 1 ? 5 : 4) : null,
                    'rated_at' => $rated ? now()->subDays(4) : null,
                ]);
            }
        }
    }
}
