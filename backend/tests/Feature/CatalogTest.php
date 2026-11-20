<?php

// Tests for Catalog Test.

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Models\Category;
use App\Models\Report;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    public function test_categories_are_a_list_of_id_and_name(): void
    {

        $flooding = Category::factory()->create(['name' => 'Flooding']);
        $roads = Category::factory()->create(['name' => 'Road Damage']);

        $this->getJson('/api/categories')->assertOk()->assertExactJson([
            ['id' => $flooding->id, 'name' => 'Flooding'],
            ['id' => $roads->id, 'name' => 'Road Damage'],
        ]);
    }

    public function test_stats_count_only_what_the_public_can_see(): void
    {
        $category = $this->category();
        Report::factory()->count(2)->create(['category_id' => $category->id]);
        Report::factory()->withTask(TaskStatus::Accepted)->create(['category_id' => $category->id]);
        Report::factory()->withTask(TaskStatus::Complete)->create(['category_id' => $category->id]);
        Report::factory()->create(['category_id' => $category->id, 'status' => 'cancelled']);
        Report::factory()->guestPending()->create(['category_id' => $category->id]);
        Report::factory()->guestRejected()->create(['category_id' => $category->id]);

        $this->getJson('/api/stats')->assertOk()->assertExactJson([
            'total' => 5, 'open' => 2, 'in_progress' => 1, 'resolved' => 1,
        ]);
    }

    public function test_stats_are_cached_but_a_change_shows_up_at_once(): void
    {
        $category = $this->category();
        Report::factory()->create(['category_id' => $category->id]);
        $this->getJson('/api/stats')->assertJsonPath('total', 1);
        $this->assertTrue(Cache::has('actify.stats'));

        $report = Report::factory()->create(['category_id' => $category->id]);
        $this->getJson('/api/stats')->assertJsonPath('total', 2);

        $report->delete();
        $this->getJson('/api/stats')->assertJsonPath('total', 1);
    }

    public function test_rankings_order_and_numbers(): void
    {
        $category = $this->category();
        $juan = $this->makeUser(Role::Volunteer, ['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);
        $ana = $this->makeUser(Role::Volunteer, ['first_name' => 'Ana', 'last_name' => 'Reyes']);
        $newbie = $this->makeUser(Role::Volunteer);


        Report::factory()->withTask(TaskStatus::Complete, $juan, 5)->create(['category_id' => $category->id, 'barangay' => 'Apokon']);
        Report::factory()->withTask(TaskStatus::Complete, $juan, 4)->create(['category_id' => $category->id, 'barangay' => 'Apokon']);
        Report::factory()->withTask(TaskStatus::Complete, $juan, null)->create(['category_id' => $category->id, 'barangay' => 'Busaon']);
        Report::factory()->withTask(TaskStatus::Complete, $ana, 5)->create(['category_id' => $category->id, 'barangay' => 'Madaum']);

        Report::factory()->withTask(TaskStatus::InProgress, $newbie)->create(['category_id' => $category->id]);

        $rows = $this->getJson('/api/volunteers/rankings')->assertOk()->json();

        $this->assertCount(2, $rows, 'volunteers with nothing finished are not listed');
        $this->assertSame($juan->id, $rows[0]['id']);
        $this->assertSame(
            ['first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'barangay' => 'Apokon', 'completed_count' => 3, 'average_rating' => 4.5, 'ratings_count' => 2],
            collect($rows[0])->except('id')->all(),
        );
        $this->assertSame(1, $rows[1]['completed_count']);
        $this->assertSame(5, $rows[1]['average_rating']);
    }

    public function test_a_volunteer_with_no_ratings_has_a_null_average(): void
    {
        $juan = $this->makeUser(Role::Volunteer);
        Report::factory()->withTask(TaskStatus::Complete, $juan, null)->create(['category_id' => $this->category()->id]);

        $this->getJson('/api/volunteers/rankings')->assertOk()
            ->assertJsonPath('0.average_rating', null)->assertJsonPath('0.ratings_count', 0);
    }

    public function test_rankings_can_be_narrowed_to_one_barangay(): void
    {
        $category = $this->category();
        $juan = $this->makeUser(Role::Volunteer);
        $ana = $this->makeUser(Role::Volunteer);
        Report::factory()->withTask(TaskStatus::Complete, $juan, 5)->count(2)->create(['category_id' => $category->id, 'barangay' => 'Apokon']);
        Report::factory()->withTask(TaskStatus::Complete, $ana, 5)->create(['category_id' => $category->id, 'barangay' => 'Madaum']);

        $rows = $this->getJson('/api/volunteers/rankings?barangay=madaum')->assertOk()->json();
        $this->assertCount(1, $rows);
        $this->assertSame($ana->id, $rows[0]['id']);
        $this->getJson('/api/volunteers/rankings?limit=0')->assertUnprocessable();
    }
}
