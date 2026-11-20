<?php

// Tests for Workflow Test.

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Report;
use App\Models\ReportTask;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    private User $maria;

    private User $juan;

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->maria = $this->makeUser(Role::Resident);
        $this->juan = $this->makeUser(Role::Volunteer, ['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);
        $this->ana = $this->makeUser(Role::Volunteer);
    }

    private function openReport(array $attributes = []): Report
    {
        return Report::factory()->create(array_merge(['user_id' => $this->maria->id, 'category_id' => $this->category()->id], $attributes));
    }

    private function complete(Report $report, array $body = []): TestResponse
    {
        return $this->post("/api/reports/{$report->id}/complete", array_merge(['note' => 'Cleared the drain.', 'confirm' => '1'], $body), ['Accept' => 'application/json']);
    }



    public function test_a_volunteer_accepts_a_report(): void
    {
        $report = $this->openReport();
        $this->signIn($this->juan);

        $this->postJson("/api/reports/{$report->id}/accept")->assertOk()
            ->assertJsonPath('status', 'in_progress')
            ->assertJsonPath('task.status', 'accepted')
            ->assertJsonPath('task.volunteer.id', $this->juan->id)
            ->assertJsonPath('task.volunteer.first_name', 'Juan');
        $this->assertDatabaseHas('report_tasks', ['report_id' => $report->id, 'volunteer_id' => $this->juan->id, 'status' => 'accepted']);
    }

    public function test_the_second_volunteer_gets_409_and_the_first_keeps_the_task(): void
    {
        $report = $this->openReport();

        $this->signIn($this->juan)->postJson("/api/reports/{$report->id}/accept")->assertOk();
        $this->signIn($this->ana)->postJson("/api/reports/{$report->id}/accept")
            ->assertStatus(409)->assertJsonPath('message', 'This request was already taken.');

        $this->assertDatabaseCount('report_tasks', 1);
        $this->assertSame($this->juan->id, ReportTask::first()->volunteer_id);
    }

    public function test_the_database_itself_refuses_two_tasks_for_one_report(): void
    {
        $report = $this->openReport();
        ReportTask::create(['report_id' => $report->id, 'volunteer_id' => $this->juan->id, 'status' => 'accepted', 'accepted_at' => now()]);

        $this->expectException(UniqueConstraintViolationException::class);
        ReportTask::create(['report_id' => $report->id, 'volunteer_id' => $this->ana->id, 'status' => 'accepted', 'accepted_at' => now()]);
    }

    public function test_a_report_that_is_not_open_cannot_be_accepted(): void
    {
        $this->signIn($this->juan);

        foreach (['resolved', 'cancelled'] as $status) {
            $report = $this->openReport(['status' => $status]);
            $this->postJson("/api/reports/{$report->id}/accept")->assertStatus(409)->assertJsonPath('message', 'This request is not open any more.');
        }
        $this->assertDatabaseCount('report_tasks', 0);
    }

    public function test_a_hidden_guest_report_cannot_be_accepted(): void
    {
        $guest = Report::factory()->guestPending()->create(['category_id' => $this->category()->id]);
        $this->signIn($this->juan)->postJson("/api/reports/{$guest->id}/accept")->assertForbidden();
    }

    public function test_a_volunteer_can_hold_only_five_unfinished_tasks(): void
    {
        foreach (range(1, 5) as $i) {
            Report::factory()->withTask(TaskStatus::Accepted, $this->juan)->create(['category_id' => $this->category()->id]);
        }
        $sixth = $this->openReport();
        $this->signIn($this->juan);

        $this->postJson("/api/reports/{$sixth->id}/accept")->assertStatus(409)
            ->assertJsonPath('message', 'You already have 5 unfinished tasks. Finish one before taking another.');


        Report::factory()->withTask(TaskStatus::Complete, $this->juan)->create(['category_id' => $this->category()->id]);
        ReportTask::where('volunteer_id', $this->juan->id)->where('status', 'accepted')->first()->update(['status' => 'complete']);
        $this->postJson("/api/reports/{$sixth->id}/accept")->assertOk();
    }

    public function test_residents_and_admins_cannot_accept(): void
    {
        $report = $this->openReport();

        $this->postJson("/api/reports/{$report->id}/accept")->assertUnauthorized();
        $this->signIn($this->maria)->postJson("/api/reports/{$report->id}/accept")->assertForbidden();
        $this->signIn($this->makeUser(Role::Admin))->postJson("/api/reports/{$report->id}/accept")->assertForbidden();
        $this->assertDatabaseCount('report_tasks', 0);
    }



    public function test_start_moves_the_task_to_in_progress_once(): void
    {
        $report = Report::factory()->withTask(TaskStatus::Accepted, $this->juan)->create(['category_id' => $this->category()->id]);
        $this->signIn($this->juan);

        $this->postJson("/api/reports/{$report->id}/start")->assertOk()->assertJsonPath('task.status', 'in_progress')->assertJsonPath('status', 'in_progress');
        $this->postJson("/api/reports/{$report->id}/start")->assertStatus(409)->assertJsonPath('message', 'This task was already started.');
    }

    public function test_only_the_volunteer_who_holds_the_task_can_start_or_complete_it(): void
    {
        $report = Report::factory()->withTask(TaskStatus::InProgress, $this->juan)->create(['category_id' => $this->category()->id]);
        $this->signIn($this->ana);

        $this->postJson("/api/reports/{$report->id}/start")->assertForbidden();
        $this->complete($report)->assertForbidden();
        $this->assertDatabaseHas('report_tasks', ['report_id' => $report->id, 'status' => 'in_progress']);
    }

    public function test_start_on_a_report_nobody_accepted_is_refused(): void
    {
        $report = $this->openReport();
        $this->signIn($this->juan)->postJson("/api/reports/{$report->id}/start")->assertForbidden();
    }



    public function test_complete_needs_a_note_and_a_confirmation(): void
    {
        $report = Report::factory()->withTask(TaskStatus::InProgress, $this->juan)->create(['category_id' => $this->category()->id]);
        $this->signIn($this->juan);

        $this->complete($report, ['note' => ''])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->complete($report, ['note' => 'ok'])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->complete($report, ['note' => str_repeat('x', 1001)])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->post("/api/reports/{$report->id}/complete", ['note' => 'Cleared the drain.'], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('errors.confirm.0', 'Please confirm this action.');
        $this->complete($report, ['confirm' => '0'])->assertUnprocessable()->assertJsonValidationErrors('confirm');

        $this->assertDatabaseHas('report_tasks', ['report_id' => $report->id, 'status' => 'in_progress']);
        $this->assertSame('in_progress', $report->fresh()->status->value);
    }

    public function test_complete_resolves_the_report_and_keeps_note_and_photo(): void
    {
        $report = Report::factory()->withTask(TaskStatus::InProgress, $this->juan)->create(['category_id' => $this->category()->id]);
        $this->signIn($this->juan);

        $response = $this->complete($report, ['photo' => UploadedFile::fake()->image('after.jpg', 2400, 1800)])->assertOk()
            ->assertJsonPath('status', 'resolved')
            ->assertJsonPath('task.status', 'complete')
            ->assertJsonPath('task.outcome_note', 'Cleared the drain.')
            ->assertJsonPath('task.rating', null);

        $this->assertStringStartsWith('https://res.cloudinary.com/actify-test/image/upload/actify/volunteer-outcomes/', $response->json('task.outcome_image_url'));
        [$w, $h] = getimagesizefromstring($this->fakeCloudinary()->bytesForReference(ReportTask::first()->outcome_image_path));
        $this->assertSame(1600, max($w, $h));
        $this->assertNotNull($response->json('task.completed_at'));
    }

    public function test_complete_without_a_photo_is_fine(): void
    {
        $report = Report::factory()->withTask(TaskStatus::InProgress, $this->juan)->create(['category_id' => $this->category()->id]);
        $this->signIn($this->juan);

        $this->complete($report)->assertOk()->assertJsonPath('task.outcome_image_url', null);
    }

    public function test_cannot_complete_before_starting_or_twice(): void
    {
        $fresh = Report::factory()->withTask(TaskStatus::Accepted, $this->juan)->create(['category_id' => $this->category()->id]);
        $done = Report::factory()->withTask(TaskStatus::Complete, $this->juan)->create(['category_id' => $this->category()->id]);
        $this->signIn($this->juan);

        $this->complete($fresh)->assertStatus(409)->assertJsonPath('message', 'Press Start before you finish the task.');
        $this->complete($done)->assertStatus(409)->assertJsonPath('message', 'This task is already finished.');
    }

    public function test_a_bad_photo_on_complete_changes_nothing(): void
    {
        $report = Report::factory()->withTask(TaskStatus::InProgress, $this->juan)->create(['category_id' => $this->category()->id]);
        $this->signIn($this->juan);

        $this->complete($report, ['photo' => UploadedFile::fake()->createWithContent('x.jpg', 'not a picture')])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->assertDatabaseHas('report_tasks', ['report_id' => $report->id, 'status' => 'in_progress']);
    }



    private function rate(Report $report, array $body = []): TestResponse
    {
        return $this->postJson("/api/reports/{$report->id}/rating", array_merge(['rating' => 5, 'confirm' => true], $body));
    }

    public function test_the_resident_rates_once_the_work_is_complete(): void
    {
        $report = Report::factory()->withTask(TaskStatus::Complete, $this->juan)->create(['user_id' => $this->maria->id, 'category_id' => $this->category()->id]);
        $this->signIn($this->maria);

        $this->rate($report, ['rating' => 4])->assertOk()->assertJsonPath('task.rating', 4);
        $this->assertDatabaseHas('report_tasks', ['report_id' => $report->id, 'rating' => 4]);
    }

    public function test_a_rating_needs_confirmation(): void
    {
        $report = Report::factory()->withTask(TaskStatus::Complete, $this->juan)->create(['user_id' => $this->maria->id, 'category_id' => $this->category()->id]);
        $this->signIn($this->maria);

        $this->postJson("/api/reports/{$report->id}/rating", ['rating' => 5])->assertUnprocessable()->assertJsonPath('errors.confirm.0', 'Please confirm this action.');
        $this->assertDatabaseHas('report_tasks', ['report_id' => $report->id, 'rating' => null]);
    }

    public function test_a_rating_is_a_whole_number_from_one_to_five(): void
    {
        $report = Report::factory()->withTask(TaskStatus::Complete, $this->juan)->create(['user_id' => $this->maria->id, 'category_id' => $this->category()->id]);
        $this->signIn($this->maria);

        foreach ([0, 6, -1, 'five', 2.5, null] as $bad) {
            $this->rate($report, ['rating' => $bad])->assertUnprocessable()->assertJsonValidationErrors('rating');
        }
        $this->assertDatabaseHas('report_tasks', ['report_id' => $report->id, 'rating' => null]);
    }

    public function test_cannot_rate_before_the_task_is_complete(): void
    {
        $this->signIn($this->maria);

        foreach ([TaskStatus::Accepted, TaskStatus::InProgress] as $status) {
            $report = Report::factory()->withTask($status, $this->juan)->create(['user_id' => $this->maria->id, 'category_id' => $this->category()->id]);
            $this->rate($report)->assertUnprocessable()->assertJsonPath('errors.rating.0', 'You can rate the volunteer once the work is finished.');
        }
        $this->rate($this->openReport())->assertUnprocessable();
    }

    public function test_a_report_can_only_be_rated_once(): void
    {
        $report = Report::factory()->withTask(TaskStatus::Complete, $this->juan, 3)->create(['user_id' => $this->maria->id, 'category_id' => $this->category()->id]);
        $this->signIn($this->maria);

        $this->rate($report, ['rating' => 5])->assertUnprocessable()->assertJsonPath('errors.rating.0', 'You already rated this report.');
        $this->assertDatabaseHas('report_tasks', ['report_id' => $report->id, 'rating' => 3]);
    }

    public function test_only_the_resident_who_filed_the_report_can_rate(): void
    {
        $report = Report::factory()->withTask(TaskStatus::Complete, $this->juan)->create(['user_id' => $this->maria->id, 'category_id' => $this->category()->id]);

        $this->rate($report)->assertUnauthorized();
        $this->signIn($this->makeUser(Role::Resident))->rate($report)->assertForbidden();
        $this->signIn($this->juan)->rate($report)->assertForbidden();
        $this->signIn($this->makeUser(Role::Admin))->rate($report)->assertForbidden();
        $this->assertDatabaseHas('report_tasks', ['report_id' => $report->id, 'rating' => null]);
    }

    public function test_a_guest_report_cannot_be_rated_by_anyone(): void
    {
        $guest = Report::factory()->guestApproved()->withTask(TaskStatus::Complete, $this->juan)->create(['category_id' => $this->category()->id]);

        foreach ([$this->maria, $this->juan, $this->makeUser(Role::Admin)] as $user) {
            $this->signIn($user)->rate($guest)->assertForbidden();
        }
    }

    public function test_the_whole_story_from_filing_to_rating(): void
    {
        $this->signIn($this->maria);
        $id = $this->post('/api/reports', $this->reportPayload(), ['Accept' => 'application/json'])->assertCreated()->json('id');

        $this->signIn($this->juan);
        $this->postJson("/api/reports/$id/accept")->assertOk()->assertJsonPath('status', 'in_progress');
        $this->postJson("/api/reports/$id/start")->assertOk();
        $this->post("/api/reports/$id/complete", ['note' => 'All clear.', 'confirm' => 1], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('status', 'resolved');

        $this->signIn($this->maria);
        $this->postJson("/api/reports/$id/rating", ['rating' => 5, 'confirm' => true])->assertOk()->assertJsonPath('task.rating', 5);

        $this->signOut();
        $this->getJson('/api/stats')->assertJsonPath('resolved', 1);
        $this->getJson('/api/volunteers/rankings')->assertJsonPath('0.id', $this->juan->id)->assertJsonPath('0.average_rating', 5);
    }
}
