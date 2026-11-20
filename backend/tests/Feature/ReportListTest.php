<?php

// Tests for Report List Test.

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Category;
use App\Models\Report;
use Tests\TestCase;

class ReportListTest extends TestCase
{
    private Category $flooding;
    private Category $roads;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flooding = Category::factory()->create(['name' => 'Flooding']);
        $this->roads = Category::factory()->create(['name' => 'Road Damage']);
    }

    private function titles(string $query = ''): array
    {
        return collect($this->getJson('/api/reports'.$query)->assertOk()->json())->pluck('title')->all();
    }

    private function make(string $title, array $attributes = []): Report
    {
        return Report::factory()->create(array_merge(['title' => $title, 'category_id' => $this->flooding->id], $attributes));
    }

    public function test_a_report_has_every_field_the_app_needs(): void
    {
        $volunteer = $this->makeUser(Role::Volunteer, ['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);
        $resident = $this->makeUser(Role::Resident, ['first_name' => 'Maria', 'last_name' => 'Santos']);
        $report = Report::factory()->withTask(TaskStatus::Complete, $volunteer, 5)->create([
            'user_id' => $resident->id, 'category_id' => $this->flooding->id, 'title' => 'Flooded road',
            'barangay' => 'Apokon', 'latitude' => 7.4631, 'longitude' => 125.8112,
        ]);

        $this->getJson("/api/reports/{$report->id}")->assertOk()
            ->assertJsonPath('id', $report->id)
            ->assertJsonPath('category', ['id' => $this->flooding->id, 'name' => 'Flooding'])
            ->assertJsonPath('category_id', $this->flooding->id)
            ->assertJsonPath('status', 'resolved')
            ->assertJsonPath('latitude', 7.4631)
            ->assertJsonPath('longitude', 125.8112)
            ->assertJsonPath('source', 'account')
            ->assertJsonPath('user', ['id' => $resident->id, 'first_name' => 'Maria', 'last_name' => 'Santos'])
            ->assertJsonPath('task.status', 'complete')
            ->assertJsonPath('task.volunteer', ['id' => $volunteer->id, 'first_name' => 'Juan', 'last_name' => 'Dela Cruz'])
            ->assertJsonPath('task.rating', 5)
            ->assertJsonPath('task.outcome_note', 'Cleared and cleaned.')
            ->assertJsonMissingPath('reporter')
            ->assertJsonMissingPath('reference');
    }

    public function test_an_open_report_has_a_null_task(): void
    {
        $report = $this->make('Open one');
        $this->getJson("/api/reports/{$report->id}")->assertOk()->assertJsonPath('task', null);
    }

    public function test_newest_first_by_default(): void
    {
        $this->make('old', ['created_at' => now()->subDays(3)]);
        $this->make('new', ['created_at' => now()->subDay()]);
        $this->make('middle', ['created_at' => now()->subDays(2)]);

        $this->assertSame(['new', 'middle', 'old'], $this->titles());
        $this->assertSame(['old', 'middle', 'new'], $this->titles('?sort=oldest'));
    }

    public function test_guest_reports_that_are_pending_or_rejected_are_not_listed_but_approved_ones_are(): void
    {
        $this->make('normal');
        Report::factory()->guestPending()->create(['title' => 'pending guest', 'category_id' => $this->flooding->id]);
        Report::factory()->guestRejected()->create(['title' => 'rejected guest', 'category_id' => $this->flooding->id]);
        Report::factory()->guestApproved()->create(['title' => 'approved guest', 'category_id' => $this->flooding->id]);

        $this->assertEqualsCanonicalizing(['normal', 'approved guest'], $this->titles());
    }

    public function test_the_public_cannot_open_a_pending_guest_report(): void
    {
        $report = Report::factory()->guestPending()->create(['category_id' => $this->flooding->id]);

        $this->getJson("/api/reports/{$report->id}")->assertNotFound();
        $this->signIn($this->makeUser(Role::Volunteer));
        $this->getJson("/api/reports/{$report->id}")->assertNotFound();
        $this->signIn($this->makeUser(Role::Admin));
        $this->getJson("/api/reports/{$report->id}")->assertOk()->assertJsonPath('source', 'guest');
    }

    public function test_an_unknown_or_non_numeric_id_is_a_clean_404(): void
    {
        $this->getJson('/api/reports/9999')->assertNotFound()->assertExactJson(['message' => 'We could not find that.']);
        $this->getJson('/api/reports/abc')->assertNotFound();
    }

    public function test_filter_by_status_one_or_several(): void
    {
        $this->make('o', ['status' => 'open']);
        $this->make('p', ['status' => 'in_progress']);
        $this->make('r', ['status' => 'resolved']);
        $this->make('c', ['status' => 'cancelled']);

        $this->assertSame(['o'], $this->titles('?status=open'));
        $this->assertEqualsCanonicalizing(['o', 'p'], $this->titles('?status=open,in_progress'));
        $this->getJson('/api/reports?status=nonsense')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_filter_by_category_urgency_type_and_barangay(): void
    {
        $this->make('a', ['category_id' => $this->roads->id, 'urgency' => 'high', 'assistance_type' => 'repair', 'barangay' => 'Apokon']);
        $this->make('b', ['category_id' => $this->flooding->id, 'urgency' => 'low', 'assistance_type' => 'cleanup', 'barangay' => 'Madaum']);
        $this->make('c', ['category_id' => $this->flooding->id, 'urgency' => 'medium', 'assistance_type' => 'supplies', 'barangay' => 'Apokon']);

        $this->assertSame(['a'], $this->titles('?category_id='.$this->roads->id));
        $this->assertSame(['a'], $this->titles('?urgency=high'));
        $this->assertEqualsCanonicalizing(['a', 'c'], $this->titles('?urgency=high,medium'));
        $this->assertSame(['b'], $this->titles('?assistance_type=cleanup'));
        $this->assertEqualsCanonicalizing(['a', 'c'], $this->titles('?barangay=apokon'), 'barangay ignores upper/lower case');
        $this->assertSame(['c'], $this->titles('?barangay=Apokon&category_id='.$this->flooding->id), 'filters combine');
        $this->getJson('/api/reports?category_id=999')->assertUnprocessable();
        $this->getJson('/api/reports?urgency=extreme')->assertUnprocessable();
    }

    public function test_filter_by_date_range_including_both_end_days(): void
    {
        $this->make('early', ['created_at' => '2026-03-01 10:00:00']);
        $this->make('mid', ['created_at' => '2026-03-10 23:59:00']);
        $this->make('late', ['created_at' => '2026-03-20 00:00:00']);

        $this->assertEqualsCanonicalizing(['mid', 'late'], $this->titles('?from=2026-03-10'));
        $this->assertEqualsCanonicalizing(['early', 'mid'], $this->titles('?to=2026-03-10'));
        $this->assertSame(['mid'], $this->titles('?from=2026-03-10&to=2026-03-10'));
        $this->getJson('/api/reports?from=2026-03-10&to=2026-03-01')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson('/api/reports?from=10/03/2026')->assertUnprocessable()->assertJsonValidationErrors('from');
    }

    public function test_search_finds_words_in_title_description_barangay_and_category(): void
    {
        $this->make('Pothole near the school', ['description' => 'Deep hole', 'barangay' => 'Apokon', 'category_id' => $this->roads->id]);
        $this->make('Water everywhere', ['description' => 'Flooded after the typhoon', 'barangay' => 'Madaum', 'category_id' => $this->flooding->id]);
        $this->make('Broken bench', ['description' => 'Park bench', 'barangay' => 'Busaon', 'category_id' => $this->roads->id]);

        $this->assertSame(['Pothole near the school'], $this->titles('?q=pothole'));
        $this->assertSame(['Water everywhere'], $this->titles('?q=typhoon'));
        $this->assertSame(['Broken bench'], $this->titles('?q=BUSAON'), 'barangay, any case');
        $this->assertEqualsCanonicalizing(['Pothole near the school', 'Broken bench'], $this->titles('?q=road'), 'category name');
        $this->assertSame(['Water everywhere'], $this->titles('?q=flood+madaum'), 'every word must match, in any field');
        $this->assertSame([], $this->titles('?q=flood+apokon'));
        $this->assertCount(3, $this->titles('?q='), 'an empty search shows everything');
    }

    public function test_search_by_report_number(): void
    {
        $a = $this->make('First');
        $b = $this->make('Second');

        $this->assertSame(['Second'], $this->titles("?q=%23{$b->id}"), '#2');
        $this->assertContains('First', $this->titles("?q={$a->id}"));
    }

    public function test_search_treats_percent_and_underscore_as_plain_characters(): void
    {
        $this->make('Fully 100% blocked road');
        $this->make('Something else entirely');

        $this->assertSame(['Fully 100% blocked road'], $this->titles('?q=100%25'));
        $this->assertSame([], $this->titles('?q=%25%25'), 'two percent signs are not "match anything"');
        $this->assertSame([], $this->titles('?q=_____'));
    }

    public function test_search_cannot_be_used_for_sql_injection(): void
    {
        $this->make('Normal');

        $this->assertSame([], $this->titles("?q=".urlencode("' OR 1=1 --")));
        $this->assertSame(['Normal'], $this->titles());
    }

    public function test_admins_can_search_a_guest_reference_but_others_cannot(): void
    {
        $guest = Report::factory()->guestPending()->create(['title' => 'Guest problem', 'category_id' => $this->flooding->id, 'reference' => 'ACT-424242']);
        $this->signIn($this->makeUser(Role::Admin));

        $this->assertSame(['Guest problem'], collect($this->getJson('/api/reports?review=pending&q=ACT-424242')->assertOk()->json())->pluck('title')->all());
        $this->assertSame($guest->id, $this->getJson('/api/reports?review=pending&q=424242')->json('0.id'));

        $this->signIn($this->makeUser(Role::Volunteer));
        $guest->update(['review_status' => 'approved', 'image_disk' => 'public']);
        $this->assertSame([], $this->titles('?q=ACT-424242'), 'the reference is private to admins');
    }

    public function test_sorting_by_urgency_and_by_status(): void
    {
        $this->make('low', ['urgency' => 'low', 'status' => 'resolved']);
        $this->make('high', ['urgency' => 'high', 'status' => 'cancelled']);
        $this->make('medium', ['urgency' => 'medium', 'status' => 'open']);
        $this->make('high-two', ['urgency' => 'high', 'status' => 'in_progress']);

        $order = $this->titles('?sort=urgency');
        $this->assertEqualsCanonicalizing(['high', 'high-two'], array_slice($order, 0, 2));
        $this->assertSame(['medium', 'low'], array_slice($order, 2));
        $this->assertSame(['medium', 'high-two', 'low', 'high'], $this->titles('?sort=status'));
        $this->getJson('/api/reports?sort=random')->assertUnprocessable();
    }

    public function test_paging_returns_a_plain_array_and_totals_in_headers(): void
    {
        foreach (range(1, 5) as $i) {
            $this->make("r$i", ['created_at' => now()->subMinutes($i)]);
        }

        $page2 = $this->getJson('/api/reports?limit=2&page=2')->assertOk()
            ->assertHeader('X-Total-Count', 5)->assertHeader('X-Page', 2)->assertHeader('X-Per-Page', 2);
        $this->assertSame(['r3', 'r4'], collect($page2->json())->pluck('title')->all());

        $this->assertSame(['r5'], collect($this->getJson('/api/reports?limit=2&page=3')->json())->pluck('title')->all());
        $this->assertSame([], $this->getJson('/api/reports?limit=2&page=9')->json());
        $this->getJson('/api/reports?limit=0')->assertUnprocessable();
        $this->getJson('/api/reports?limit=201')->assertUnprocessable();
        $this->getJson('/api/reports?limit=200')->assertOk();
    }

    public function test_the_total_header_counts_the_filtered_list(): void
    {
        $this->make('a', ['status' => 'open']);
        $this->make('b', ['status' => 'open']);
        $this->make('c', ['status' => 'resolved']);

        $this->getJson('/api/reports?status=open&limit=1')->assertHeader('X-Total-Count', 2);
    }

    public function test_my_reports_and_my_tasks_need_a_token(): void
    {
        $this->getJson('/api/reports?scope=mine')->assertUnauthorized();
        $this->getJson('/api/reports?scope=tasks')->assertUnauthorized();
        $this->getJson('/api/reports?review=pending')->assertUnauthorized();
    }

    public function test_scope_mine_and_scope_tasks(): void
    {
        $maria = $this->makeUser(Role::Resident);
        $juan = $this->makeUser(Role::Volunteer);
        $this->make('maria 1', ['user_id' => $maria->id]);
        $this->make('someone else');
        Report::factory()->withTask(TaskStatus::Accepted, $juan)->create(['title' => 'juan task', 'category_id' => $this->flooding->id]);
        Report::factory()->withTask(TaskStatus::Accepted)->create(['title' => 'other task', 'category_id' => $this->flooding->id]);

        $this->signIn($maria);
        $this->assertSame(['maria 1'], $this->titles('?scope=mine'));

        $this->signIn($juan);
        $this->assertSame(['juan task'], $this->titles('?scope=tasks'));
        $this->assertSame([], $this->titles('?scope=mine'));
        $this->assertCount(4, $this->titles(), 'no scope: the public list');
    }

    public function test_the_review_queue_is_for_admins_only(): void
    {
        Report::factory()->guestPending()->create(['title' => 'waiting', 'category_id' => $this->flooding->id]);
        Report::factory()->guestRejected()->create(['title' => 'rejected', 'category_id' => $this->flooding->id]);
        $this->make('normal');

        $this->signIn($this->makeUser(Role::Resident));
        $this->getJson('/api/reports?review=pending')->assertForbidden();
        $this->signIn($this->makeUser(Role::Volunteer));
        $this->getJson('/api/reports?review=pending')->assertForbidden();

        $this->signIn($this->makeUser(Role::Admin));
        $queue = $this->getJson('/api/reports?review=pending')->assertOk()->json();
        $this->assertSame(['waiting'], collect($queue)->pluck('title')->all());
        $this->assertSame('guest', $queue[0]['source']);
        $this->assertSame('Gina', $queue[0]['reporter']['first_name']);
    }

    public function test_a_bad_token_on_the_public_list_just_means_you_are_a_visitor(): void
    {
        $this->make('normal');
        $this->withToken('999|not-a-real-token')->getJson('/api/reports')->assertOk();
    }

    public function test_the_list_does_one_batch_of_queries_not_one_per_report(): void
    {
        foreach (range(1, 15) as $i) {
            Report::factory()->withTask(TaskStatus::Accepted)->create(['category_id' => $this->flooding->id]);
        }

        $count = 0;
        \DB::listen(function () use (&$count) { $count++; });
        $this->getJson('/api/reports')->assertOk();

        $this->assertLessThan(12, $count, "the list ran $count queries for 15 reports");
    }
}
