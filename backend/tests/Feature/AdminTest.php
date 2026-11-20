<?php

// Tests for Admin Test.

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeUser(Role::Admin, ['first_name' => 'Ada']);
        $this->signIn($this->admin);
    }

    private function report(array $attributes = []): Report
    {
        return Report::factory()->create(array_merge(['category_id' => $this->category()->id], $attributes));
    }



    public function test_admin_changes_status(): void
    {
        $report = $this->report();

        $this->patchJson("/api/reports/{$report->id}", ['status' => 'resolved'])->assertOk()->assertJsonPath('status', 'resolved');
        $this->assertSame('resolved', $report->fresh()->status->value);
    }

    public function test_cancelling_needs_a_confirmation_but_other_changes_do_not(): void
    {
        $report = $this->report();

        $this->patchJson("/api/reports/{$report->id}", ['status' => 'cancelled'])
            ->assertUnprocessable()->assertJsonPath('errors.confirm.0', 'Please confirm this action.');
        $this->assertSame('open', $report->fresh()->status->value);

        $this->patchJson("/api/reports/{$report->id}", ['status' => 'cancelled', 'confirm' => true])->assertOk()->assertJsonPath('status', 'cancelled');
        $this->patchJson("/api/reports/{$report->id}", ['status' => 'in_progress'])->assertOk();
    }

    public function test_an_invalid_status_is_refused(): void
    {
        $report = $this->report();
        $this->patchJson("/api/reports/{$report->id}", ['status' => 'banana'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->patchJson("/api/reports/{$report->id}", [])->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_setting_a_report_back_to_open_frees_the_volunteers_unfinished_task(): void
    {
        $report = Report::factory()->withTask(TaskStatus::Accepted)->create(['category_id' => $this->category()->id]);

        $this->patchJson("/api/reports/{$report->id}", ['status' => 'open'])->assertOk()->assertJsonPath('status', 'open')->assertJsonPath('task', null);
        $this->assertDatabaseCount('report_tasks', 0);


        $this->signIn($this->makeUser(Role::Volunteer))->postJson("/api/reports/{$report->id}/accept")->assertOk();
    }

    public function test_a_finished_request_cannot_be_reopened(): void
    {
        $report = Report::factory()->withTask(TaskStatus::Complete, null, 5)->create(['category_id' => $this->category()->id]);

        $this->patchJson("/api/reports/{$report->id}", ['status' => 'open'])->assertStatus(409)->assertJsonPath('message', 'A finished request cannot be reopened.');
        $this->assertDatabaseHas('report_tasks', ['report_id' => $report->id, 'rating' => 5]);
        $this->assertSame('resolved', $report->fresh()->status->value);
    }

    public function test_the_status_of_a_guest_report_that_is_not_approved_cannot_be_changed(): void
    {
        $pending = Report::factory()->guestPending()->create(['category_id' => $this->category()->id]);
        $rejected = Report::factory()->guestRejected()->create(['category_id' => $this->category()->id]);

        $this->patchJson("/api/reports/{$pending->id}", ['status' => 'resolved'])->assertStatus(409)->assertJsonPath('message', 'Approve or reject this guest report first.');
        $this->patchJson("/api/reports/{$rejected->id}", ['status' => 'resolved'])->assertStatus(409);
    }

    public function test_admin_status_change_on_a_missing_report_is_404(): void
    {
        $this->patchJson('/api/reports/9999', ['status' => 'open'])->assertNotFound();
    }



    public function test_deleting_a_report_needs_a_confirmation(): void
    {
        $report = $this->report();

        $this->deleteJson("/api/reports/{$report->id}")->assertUnprocessable()->assertJsonPath('errors.confirm.0', 'Please confirm this action.');
        $this->deleteJson("/api/reports/{$report->id}", ['confirm' => false])->assertUnprocessable();
        $this->deleteJson("/api/reports/{$report->id}", ['confirm' => 'no'])->assertUnprocessable();
        $this->assertDatabaseHas('reports', ['id' => $report->id]);

        $this->deleteJson("/api/reports/{$report->id}", ['confirm' => true])->assertNoContent();
        $this->assertDatabaseMissing('reports', ['id' => $report->id]);
    }

    public function test_the_confirmation_can_also_come_in_the_address(): void
    {
        $report = $this->report();
        $this->deleteJson("/api/reports/{$report->id}?confirm=1")->assertNoContent();
    }

    public function test_deleting_a_report_removes_its_task_and_all_its_pictures(): void
    {
        $juan = $this->makeUser(Role::Volunteer);
        $maria = $this->makeUser(Role::Resident);

        $this->signIn($maria)->post('/api/reports', $this->reportPayload(['photo' => UploadedFile::fake()->image('p.jpg', 200, 200)]), ['Accept' => 'application/json'])->assertCreated();
        $report = Report::first();
        $this->signIn($juan)->postJson("/api/reports/{$report->id}/accept");
        $this->postJson("/api/reports/{$report->id}/start");
        $this->post("/api/reports/{$report->id}/complete", ['note' => 'Done well.', 'confirm' => 1, 'photo' => UploadedFile::fake()->image('a.jpg', 200, 200)], ['Accept' => 'application/json'])->assertOk();
        $this->assertCount(2, Storage::disk('public')->allFiles());

        $this->signIn($this->admin)->deleteJson("/api/reports/{$report->id}", ['confirm' => true])->assertNoContent();

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('report_tasks', 0);
        $this->getJson('/api/stats')->assertJsonPath('total', 0);
    }

    public function test_deleting_a_report_with_a_missing_cloudinary_asset_does_not_crash(): void
    {
        $report = Report::factory()->create([
            'category_id' => $this->category()->id,
            'image_path' => 'cloudinary:actify/reports/missing',
            'image_disk' => 'public',
        ]);

        $this->signIn($this->admin)->deleteJson("/api/reports/{$report->id}", ['confirm' => true])->assertNoContent();

        $this->assertDatabaseMissing('reports', ['id' => $report->id]);
    }



    public function test_the_people_list_has_every_account_newest_first(): void
    {
        $old = $this->makeUser(Role::Resident, ['first_name' => 'Old']);
        $new = $this->makeUser(Role::Volunteer, ['first_name' => 'New']);

        $rows = $this->getJson('/api/admin/users')->assertOk()->assertHeader('X-Total-Count', 3)->json();

        $this->assertSame([$new->id, $old->id, $this->admin->id], array_column($rows, 'id'));
        $this->assertSame(['id', 'username', 'first_name', 'last_name', 'email', 'role', 'created_at', 'photo_url'], array_keys($rows[0]));
        $this->assertStringNotContainsString('password', json_encode($rows));
    }

    public function test_search_and_filter_people(): void
    {
        $this->makeUser(Role::Resident, ['username' => 'maria_s', 'first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'maria@example.test']);
        $this->makeUser(Role::Volunteer, ['username' => 'juan', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'juan@mail.test']);
        $this->makeUser(Role::Volunteer, ['username' => 'ana', 'first_name' => 'Ana', 'last_name' => 'Santos', 'email' => 'ana@mail.test']);

        $names = fn (string $q) => collect($this->getJson('/api/admin/users'.$q)->assertOk()->json())->pluck('first_name')->sort()->values()->all();

        $this->assertSame(['Ana', 'Maria'], $names('?q=santos'));
        $this->assertSame(['Maria'], $names('?q=maria+san'), 'every word must match');
        $this->assertSame(['Juan'], $names('?q=juan@mail'));
        $this->assertSame(['Juan'], $names('?q=JUAN'));
        $this->assertSame(['Ana', 'Juan'], $names('?role=volunteer'));
        $this->assertSame(['Ana'], $names('?role=volunteer&q=santos'));
        $this->assertSame([], $names('?q=zzzz'));
        $this->assertSame([], $names('?q=%25'), 'a percent sign is just a character');
        $this->getJson('/api/admin/users?role=wizard')->assertUnprocessable();
    }

    public function test_people_sorting_and_paging(): void
    {
        $this->makeUser(Role::Resident, ['first_name' => 'Zed']);
        $this->makeUser(Role::Resident, ['first_name' => 'Bea']);

        $first = fn (string $q) => $this->getJson('/api/admin/users'.$q)->json('0.first_name');
        $this->assertSame('Ada', $first('?sort=name'));
        $this->assertSame('Ada', $first('?sort=oldest'));
        $this->assertSame('Bea', $first('?sort=newest'), 'the last one created');
        $this->assertSame('Ada', $first('?sort=role'), 'admin sorts before resident');

        $this->getJson('/api/admin/users?limit=1&page=2')->assertOk()->assertHeader('X-Total-Count', 3)->assertJsonCount(1);
        $this->getJson('/api/admin/users?sort=sideways')->assertUnprocessable();
    }

    public function test_changing_a_role_needs_a_confirmation(): void
    {
        $maria = $this->makeUser(Role::Resident);

        $this->patchJson("/api/admin/users/{$maria->id}", ['role' => 'volunteer'])->assertUnprocessable()->assertJsonValidationErrors('confirm');
        $this->assertSame('resident', $maria->fresh()->role->value);

        $this->patchJson("/api/admin/users/{$maria->id}", ['role' => 'volunteer', 'confirm' => true])->assertOk()->assertJsonPath('role', 'volunteer');
        $this->patchJson("/api/admin/users/{$maria->id}", ['role' => 'emperor', 'confirm' => true])->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_an_admin_cannot_change_their_own_role(): void
    {
        $this->makeUser(Role::Admin);

        $this->patchJson("/api/admin/users/{$this->admin->id}", ['role' => 'resident', 'confirm' => true])
            ->assertStatus(409)->assertJsonPath('message', 'You cannot change your own role.');
        $this->assertTrue($this->admin->fresh()->isAdmin());
    }

    public function test_a_new_role_takes_effect_on_the_next_request(): void
    {
        $maria = $this->makeUser(Role::Resident);
        $token = $maria->createToken('t')->plainTextToken;
        $this->signOut();
        $this->withToken($token)->getJson('/api/admin/users')->assertForbidden();

        $this->signIn($this->admin)->patchJson("/api/admin/users/{$maria->id}", ['role' => 'admin', 'confirm' => true])->assertOk();

        $this->signOut();
        $this->withToken($token)->getJson('/api/admin/users')->assertOk();
    }

    public function test_demoting_a_volunteer_hands_their_unfinished_tasks_back(): void
    {
        $juan = $this->makeUser(Role::Volunteer);
        $open = Report::factory()->withTask(TaskStatus::InProgress, $juan)->create(['category_id' => $this->category()->id]);
        $done = Report::factory()->withTask(TaskStatus::Complete, $juan, 5)->create(['category_id' => $this->category()->id]);

        $this->patchJson("/api/admin/users/{$juan->id}", ['role' => 'resident', 'confirm' => true])->assertOk();

        $this->assertSame('open', $open->fresh()->status->value);
        $this->assertNull($open->fresh()->task);
        $this->assertSame('resolved', $done->fresh()->status->value, 'finished work stays');
        $this->assertSame(5, $done->fresh()->task->rating);
    }

    public function test_deleting_a_person_needs_a_confirmation(): void
    {
        $maria = $this->makeUser(Role::Resident);

        $this->deleteJson("/api/admin/users/{$maria->id}")->assertUnprocessable()->assertJsonPath('errors.confirm.0', 'Please confirm this action.');
        $this->assertDatabaseHas('users', ['id' => $maria->id]);

        $this->deleteJson("/api/admin/users/{$maria->id}", ['confirm' => true])->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $maria->id]);
    }

    public function test_deleting_a_person_keeps_their_reports_signs_them_out_and_removes_their_picture(): void
    {
        $maria = $this->makeUser(Role::Resident);
        $token = $maria->createToken('t')->plainTextToken;
        $report = Report::factory()->create(['user_id' => $maria->id, 'category_id' => $this->category()->id]);
        $this->signIn($maria)->post('/api/auth/me/photo', ['photo' => UploadedFile::fake()->image('a.jpg', 100, 100)], ['Accept' => 'application/json'])->assertOk();
        $avatar = $maria->fresh()->photo_path;

        $this->signIn($this->admin)->deleteJson("/api/admin/users/{$maria->id}", ['confirm' => true])->assertNoContent();

        $this->assertDatabaseHas('reports', ['id' => $report->id, 'user_id' => null]);
        $this->assertFalse($this->fakeCloudinary()->existsForReference($avatar));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->signOut();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_deleting_a_volunteer_frees_their_unfinished_tasks(): void
    {
        $juan = $this->makeUser(Role::Volunteer);
        $report = Report::factory()->withTask(TaskStatus::Accepted, $juan)->create(['category_id' => $this->category()->id]);

        $this->deleteJson("/api/admin/users/{$juan->id}", ['confirm' => true])->assertNoContent();

        $this->assertSame('open', $report->fresh()->status->value);
        $this->assertDatabaseCount('report_tasks', 0);
    }

    public function test_an_admin_cannot_delete_themselves(): void
    {
        $this->deleteJson("/api/admin/users/{$this->admin->id}", ['confirm' => true])
            ->assertStatus(409)->assertJsonPath('message', 'You cannot remove your own account here.');
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
    }

    public function test_one_admin_can_remove_another_but_the_last_admin_is_protected(): void
    {
        $other = $this->makeUser(Role::Admin);
        $this->deleteJson("/api/admin/users/{$other->id}", ['confirm' => true])->assertNoContent();
        $this->assertSame(1, User::where('role', 'admin')->count());


        $second = $this->makeUser(Role::Admin);
        $this->signIn($second)->deleteJson("/api/admin/users/{$this->admin->id}", ['confirm' => true])->assertNoContent();
        $this->assertSame(1, User::where('role', 'admin')->count());
        $this->assertDatabaseHas('users', ['id' => $second->id]);
    }

    public function test_an_unknown_user_is_404(): void
    {
        $this->patchJson('/api/admin/users/9999', ['role' => 'resident', 'confirm' => true])->assertNotFound();
        $this->deleteJson('/api/admin/users/9999', ['confirm' => true])->assertNotFound();
    }



    public function test_make_admin_command(): void
    {
        $maria = $this->makeUser(Role::Resident, ['username' => 'maria']);

        $this->artisan('actify:make-admin MARIA')->expectsOutputToContain('maria is now an admin.')->assertSuccessful();
        $this->assertTrue($maria->fresh()->isAdmin());

        $this->artisan('actify:make-admin nobody')->expectsOutputToContain('No account with that username')->assertFailed();
    }
}
