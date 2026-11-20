<?php

// Tests for Guest Report Test.

namespace Tests\Feature;

use App\Enums\ReviewStatus;
use App\Enums\Role;
use App\Models\GuestReporter;
use App\Models\Report;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GuestReportTest extends TestCase
{
    private function send(array $override = []): TestResponse
    {
        return $this->post('/api/reports/guest', $this->guestPayload($override), ['Accept' => 'application/json']);
    }

    private function sendOk(array $override = []): array
    {
        return $this->send($override)->assertCreated()->json();
    }



    public function test_a_visitor_files_a_report_without_a_token(): void
    {
        $body = $this->sendOk();

        $this->assertMatchesRegularExpression('/^ACT-\d{6}$/', $body['reference']);
        $this->assertStringContainsString($body['reference'], $body['message']);

        $report = Report::first();
        $this->assertSame('guest', $report->source->value);
        $this->assertSame('pending', $report->review_status->value);
        $this->assertSame('open', $report->status->value);
        $this->assertNull($report->user_id);
        $this->assertSame('private', $report->image_disk);
        $this->assertSame($body['reference'], $report->reference);

        $who = GuestReporter::first();
        $this->assertSame(['Gina', 'Guest', '9123456789', 'live', 'Hold up 2 fingers'], [$who->first_name, $who->last_name, $who->phone_key, $who->photo_capture_method->value, $who->liveness_prompt]);
        $this->assertNotNull($who->consented_at);
        $this->assertNotNull($who->ip);
    }

    public function test_both_pictures_are_stored_privately_and_never_on_the_public_disk(): void
    {
        $this->sendOk();

        $report = Report::first();
        $selfie = GuestReporter::first()->selfie_path;
        Storage::disk('private')->assertExists([$report->image_path, $selfie]);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertStringStartsWith('guest-selfies/', $selfie);
    }

    public function test_the_report_is_invisible_to_the_public_until_approved(): void
    {
        $this->sendOk();
        $id = Report::first()->id;

        $this->getJson('/api/reports')->assertOk()->assertJsonCount(0);
        $this->getJson("/api/reports/$id")->assertNotFound();
        $this->getJson('/api/stats')->assertJsonPath('total', 0);
        $this->signIn($this->makeUser(Role::Resident));
        $this->getJson('/api/reports')->assertJsonCount(0);
        $this->getJson("/api/reports/$id")->assertNotFound();
    }

    public function test_required_fields_and_formats(): void
    {
        $this->post('/api/reports/guest', [], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors([
            'first_name', 'last_name', 'phone', 'reporter_barangay', 'title', 'description', 'category_id', 'barangay',
            'latitude', 'longitude', 'photo', 'photo_captured_at', 'photo_capture_method', 'selfie', 'selfie_captured_at',
            'selfie_capture_method', 'liveness_prompt', 'consent',
        ]);
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_consent_must_be_given(): void
    {
        $this->send(['consent' => '0'])->assertUnprocessable()->assertJsonPath('errors.consent.0', 'Please tick the box to agree before sending.');
        $this->send(['consent' => null])->assertUnprocessable()->assertJsonValidationErrors('consent');
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_the_usual_ways_of_writing_a_phone_number_are_accepted(): void
    {
        foreach (['0912 345 6789', '+63 912 345 6789', '(082) 221-1234', '09123456789'] as $phone) {
            $this->send(['phone' => $phone])->assertCreated();
            Report::query()->delete();
        }
    }

    public function test_things_that_are_not_phone_numbers_are_refused(): void
    {
        foreach (['abc', '12345', '0912-abc-6789', '', '+', str_repeat('1', 20)] as $bad) {
            $this->send(['phone' => $bad])->assertUnprocessable()->assertJsonValidationErrors('phone');
        }
    }

    public function test_names_cannot_contain_digits_or_symbols(): void
    {
        $this->send(['first_name' => 'Gina<script>'])->assertUnprocessable()->assertJsonValidationErrors('first_name');
        $this->send(['last_name' => '12345'])->assertUnprocessable()->assertJsonValidationErrors('last_name');
        $this->send(['email' => 'not an email'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->send(['email' => null])->assertCreated();
    }



    public function test_a_photo_that_is_too_old_is_refused(): void
    {
        $this->send(['photo_captured_at' => now()->subMinutes(30)->toISOString()])
            ->assertUnprocessable()->assertJsonValidationErrors('photo_captured_at');
        $this->send(['selfie_captured_at' => now()->subDays(3)->toISOString()])
            ->assertUnprocessable()->assertJsonValidationErrors('selfie_captured_at');
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_a_photo_from_the_future_is_refused_but_a_small_clock_difference_is_fine(): void
    {
        $this->send(['photo_captured_at' => now()->addMinutes(30)->toISOString()])->assertUnprocessable()->assertJsonValidationErrors('photo_captured_at');
        $this->send(['photo_captured_at' => now()->addMinute()->toISOString()])->assertCreated();
    }

    public function test_the_phone_camera_fallback_must_be_at_most_three_minutes_old(): void
    {
        $this->send(['photo_capture_method' => 'camera-app', 'photo_captured_at' => now()->subMinutes(5)->toISOString()])
            ->assertUnprocessable()->assertJsonValidationErrors('photo_captured_at');
        $this->send(['photo_capture_method' => 'camera-app', 'photo_captured_at' => now()->subMinutes(2)->toISOString()])->assertCreated();

        $this->send(['phone' => '0999 111 2222', 'photo_captured_at' => now()->subMinutes(5)->toISOString()])->assertCreated();
    }

    public function test_capture_method_and_date_must_be_valid(): void
    {
        $this->send(['photo_capture_method' => 'gallery'])->assertUnprocessable()->assertJsonValidationErrors('photo_capture_method');
        $this->send(['photo_captured_at' => 'yesterday-ish'])->assertUnprocessable()->assertJsonValidationErrors('photo_captured_at');
    }



    public function test_a_fake_picture_is_refused_and_leaves_nothing_behind(): void
    {
        $this->send(['selfie' => UploadedFile::fake()->createWithContent('selfie.jpg', 'definitely not a jpeg')])
            ->assertUnprocessable()->assertJsonValidationErrors('selfie');
        $this->send(['photo' => UploadedFile::fake()->createWithContent('p.jpg', "\xFF\xD8\xFF\xE0".str_repeat("\0", 100))])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');

        $this->assertDatabaseCount('reports', 0);
        $this->assertSame([], Storage::disk('private')->allFiles());
    }

    public function test_if_the_selfie_cannot_be_read_the_photo_already_saved_is_removed(): void
    {

        $badButAccepted = UploadedFile::fake()->createWithContent('selfie.jpg', "\xFF\xD8\xFF\xE0\x00\x10JFIF".str_repeat("\x01", 300));

        $this->send(['selfie' => $badButAccepted])->assertUnprocessable();

        $this->assertSame([], Storage::disk('private')->allFiles());
        $this->assertDatabaseCount('reports', 0);
    }



    public function test_an_address_may_send_three_reports_an_hour(): void
    {
        foreach (['0911 111 1111', '0922 222 2222', '0933 333 3333'] as $phone) {
            $this->sendOk(['phone' => $phone]);
        }

        $this->send(['phone' => '0944 444 4444'])
            ->assertStatus(429)->assertJsonPath('message', 'You have sent too many reports. Please try again later.');
        $this->assertDatabaseCount('reports', 3);

        $this->travel(61)->minutes();
        $this->sendOk(['phone' => '0944 444 4444', 'photo_captured_at' => now()->subMinute()->toISOString(), 'selfie_captured_at' => now()->subMinute()->toISOString()]);
    }

    public function test_a_phone_number_may_send_two_reports_a_day_however_it_is_written(): void
    {
        $this->sendOk(['phone' => '0912 345 6789']);
        $this->sendOk(['phone' => '+63 912 345 6789']);


        config(['actify.guest.per_ip_per_hour' => 99]);
        $this->send(['phone' => '0912-345-6789'])->assertStatus(429);
        $this->send(['phone' => '0922 000 1111'])->assertCreated();
    }

    public function test_rejected_reports_still_count_towards_the_limits(): void
    {
        $this->signIn($this->makeUser(Role::Admin));
        $this->signOut();
        $this->sendOk();
        $this->sendOk(['phone' => '0912 345 6789']);
        Report::query()->update(['review_status' => 'rejected']);

        config(['actify.guest.per_ip_per_hour' => 99]);
        $this->send()->assertStatus(429);
    }



    public function test_only_admins_see_who_is_behind_a_guest_report(): void
    {
        $this->sendOk();
        $id = Report::first()->id;
        Report::first()->update(['review_status' => 'approved']);

        $public = $this->getJson("/api/reports/$id")->assertOk();
        $public->assertJsonPath('source', 'guest')->assertJsonPath('user', null)->assertJsonPath('user_id', null)
            ->assertJsonMissingPath('reporter')->assertJsonMissingPath('reference');
        $this->assertStringNotContainsString('Gina', $public->getContent());
        $this->assertStringNotContainsString('0912', $public->getContent());
        $this->assertStringNotContainsString('selfie', $public->getContent());

        foreach ([Role::Resident, Role::Volunteer] as $role) {
            $this->signIn($this->makeUser($role));
            $body = $this->getJson("/api/reports/$id")->assertOk()->getContent();
            $this->assertStringNotContainsString('Gina', $body);
            $this->assertStringNotContainsString('selfie', $body);
            $this->assertStringNotContainsString('selfie', $this->getJson('/api/reports')->getContent());
        }

        $this->signIn($this->makeUser(Role::Admin));
        $this->getJson("/api/reports/$id")->assertOk()
            ->assertJsonPath('reporter.first_name', 'Gina')
            ->assertJsonPath('reporter.phone', '0912 345 6789')
            ->assertJsonPath('reporter.email', 'gina@example.test')
            ->assertJsonPath('reporter.barangay', 'Magugpo Poblacion')
            ->assertJsonPath('reporter.liveness_prompt', 'Hold up 2 fingers')
            ->assertJsonPath('reporter.photo_capture_method', 'live')
            ->assertJsonStructure(['reporter' => ['selfie_url', 'photo_captured_at', 'selfie_captured_at', 'selfie_capture_method'], 'reference']);
    }

    public function test_the_selfie_link_is_signed_short_lived_and_works_without_a_token(): void
    {
        $this->sendOk();
        $id = Report::first()->id;
        $this->signIn($this->makeUser(Role::Admin));
        $url = $this->getJson("/api/reports/$id")->json('reporter.selfie_url');

        $this->assertStringContainsString("/api/reports/$id/private/selfie?", $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringNotContainsString('/storage/', $url);

        $this->signOut();
        $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertNotEmpty($response->streamedContent());
    }

    public function test_the_selfie_link_does_not_work_when_unsigned_edited_or_expired(): void
    {
        $this->sendOk();
        $id = Report::first()->id;
        $this->signIn($this->makeUser(Role::Admin));
        $url = $this->getJson("/api/reports/$id")->json('reporter.selfie_url');
        $this->signOut();

        $this->get("/api/reports/$id/private/selfie")->assertForbidden();
        $this->get(preg_replace('/signature=[^&]+/', 'signature=abc', $url))->assertForbidden();
        $this->get(str_replace('/selfie?', '/photo?', $url))->assertForbidden();
        $other = Report::factory()->create(['category_id' => $this->category()->id]);
        $this->get(str_replace("/reports/$id/", "/reports/{$other->id}/", $url))->assertForbidden();

        $this->travel(16)->minutes();
        $this->get($url)->assertForbidden()->assertJsonPath('message', 'This link has expired. Open the report again to get a new one.');
    }

    public function test_the_pending_photo_link_is_for_admins_only_and_the_image_is_null_for_everyone_else(): void
    {
        $this->sendOk();
        $id = Report::first()->id;

        $this->signIn($this->makeUser(Role::Admin));
        $photo = $this->getJson("/api/reports/$id")->json('image_url');
        $this->assertStringContainsString("/api/reports/$id/private/photo?", $photo);
        $this->signOut();
        $this->get($photo)->assertOk();

        Report::first()->update(['review_status' => 'approved']);
        $this->signIn($this->makeUser(Role::Volunteer));
        $this->getJson("/api/reports/$id")->assertJsonPath('image_url', null);
    }



    private function review(Report $report, string $decision, array $extra = ['confirm' => true]): TestResponse
    {
        return $this->postJson("/api/reports/{$report->id}/review", ['decision' => $decision] + $extra);
    }

    public function test_approving_makes_it_public_and_moves_the_photo_but_never_the_selfie(): void
    {
        $this->sendOk();
        $report = Report::first();
        $oldPath = $report->image_path;
        $selfie = GuestReporter::first()->selfie_path;
        $admin = $this->makeUser(Role::Admin);

        $this->signIn($admin);
        $url = $this->review($report, 'approve')->assertOk()
            ->assertJsonPath('status', 'open')->assertJsonPath('source', 'guest')->json('image_url');

        $this->assertStringStartsWith('https://res.cloudinary.com/actify-test/image/upload/actify/reports/', $url);
        $report->refresh();
        $this->assertSame(['public', 'approved', $admin->id], [$report->image_disk, $report->review_status->value, $report->reviewed_by]);
        $this->assertTrue($this->fakeCloudinary()->existsForReference($report->image_path));
        Storage::disk('private')->assertMissing($oldPath);
        $this->assertCount(1, Storage::disk('public')->allFiles());

        $this->signOut();
        $this->getJson('/api/reports')->assertJsonCount(1)->assertJsonPath('0.id', $report->id);
        $this->getJson('/api/stats')->assertJsonPath('total', 1);
    }

    public function test_a_database_failure_during_approval_keeps_the_private_original(): void
    {
        $this->sendOk();
        $report = Report::first();
        $privatePath = $report->image_path;
        $this->signIn($this->makeUser(Role::Admin));
        Report::saving(function (Report $candidate) use ($report) {
            if ($candidate->is($report) && $candidate->review_status === ReviewStatus::Approved) {
                return false;
            }

            return null;
        });

        $this->review($report, 'approve')->assertStatus(500);

        $this->assertSame(['private', 'pending'], [$report->fresh()->image_disk, $report->fresh()->review_status->value]);
        Storage::disk('private')->assertExists($privatePath);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_rejecting_keeps_it_hidden(): void
    {
        $this->sendOk();
        $report = Report::first();
        $this->signIn($this->makeUser(Role::Admin));

        $this->review($report, 'reject')->assertOk();

        $this->assertSame('rejected', $report->fresh()->review_status->value);
        $this->signOut();
        $this->getJson('/api/reports')->assertJsonCount(0);
        $this->getJson("/api/reports/{$report->id}")->assertNotFound();
    }

    public function test_a_review_needs_confirmation_and_a_valid_decision(): void
    {
        $this->sendOk();
        $report = Report::first();
        $this->signIn($this->makeUser(Role::Admin));

        $this->review($report, 'approve', [])->assertUnprocessable()->assertJsonPath('errors.confirm.0', 'Please confirm this action.');
        $this->review($report, 'reject', [])->assertUnprocessable()->assertJsonValidationErrors('confirm');
        $this->review($report, 'maybe')->assertUnprocessable()->assertJsonValidationErrors('decision');
        $this->assertSame('pending', $report->fresh()->review_status->value);
    }

    public function test_a_report_is_reviewed_only_once_and_only_guest_reports_are_reviewed(): void
    {
        $this->sendOk();
        $guest = Report::first();
        $normal = Report::factory()->create(['category_id' => $this->category()->id]);
        $this->signIn($this->makeUser(Role::Admin));

        $this->review($guest, 'approve')->assertOk();
        $this->review($guest, 'reject')->assertStatus(409)->assertJsonPath('message', 'This report was already reviewed.');
        $this->review($normal, 'approve')->assertStatus(409);
    }

    public function test_only_admins_review(): void
    {
        $this->sendOk();
        $report = Report::first();

        $this->review($report, 'approve')->assertUnauthorized();
        foreach ([Role::Resident, Role::Volunteer] as $role) {
            $this->signIn($this->makeUser($role));
            $this->review($report, 'approve')->assertForbidden();
        }
        $this->assertSame('pending', $report->fresh()->review_status->value);
    }

    public function test_deleting_a_guest_report_removes_the_selfie_too(): void
    {
        $this->sendOk();
        $report = Report::first();
        $selfie = GuestReporter::first()->selfie_path;
        $this->signIn($this->makeUser(Role::Admin));

        $this->deleteJson("/api/reports/{$report->id}", ['confirm' => true])->assertNoContent();

        $this->assertSame([], Storage::disk('private')->allFiles());
        $this->assertDatabaseCount('guest_reporters', 0);
        Storage::disk('private')->assertMissing($selfie);
    }



    public function test_old_rejected_guest_reports_are_purged_with_their_files(): void
    {
        $this->sendOk(['phone' => '0911 111 1111']);
        $this->sendOk(['phone' => '0922 222 2222']);
        [$old, $recent] = Report::orderBy('id')->get()->all();
        $old->forceFill(['review_status' => 'rejected', 'reviewed_at' => now()->subDays(40)])->save();
        $recent->forceFill(['review_status' => 'rejected', 'reviewed_at' => now()->subDays(5)])->save();

        $this->artisan('actify:purge-rejected-guest-reports')->expectsOutputToContain('Deleted 1 rejected guest report')->assertSuccessful();

        $this->assertDatabaseMissing('reports', ['id' => $old->id]);
        $this->assertDatabaseHas('reports', ['id' => $recent->id]);
        $this->assertCount(2, Storage::disk('private')->allFiles(), 'only the recent report keeps its photo and selfie');
    }

    public function test_the_purge_never_touches_pending_or_approved_reports(): void
    {
        $this->sendOk();
        Report::first()->forceFill(['reviewed_at' => now()->subYear()])->save();

        $this->artisan('actify:purge-rejected-guest-reports --days=0')->assertSuccessful();

        $this->assertDatabaseCount('reports', 1);
    }
}
