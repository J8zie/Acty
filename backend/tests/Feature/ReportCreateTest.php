<?php

// Tests for Report Create Test.

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Report;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReportCreateTest extends TestCase
{
    private function post_report(array $body): TestResponse
    {
        return $this->post('/api/reports', $body, ['Accept' => 'application/json']);
    }

    public function test_a_resident_files_a_report(): void
    {
        $maria = $this->makeUser(Role::Resident, ['first_name' => 'Maria', 'last_name' => 'Santos']);
        $this->signIn($maria);

        $this->post_report($this->reportPayload())
            ->assertCreated()
            ->assertJsonPath('title', 'Clogged drain on Rizal Street')
            ->assertJsonPath('status', 'open')
            ->assertJsonPath('user_id', $maria->id)
            ->assertJsonPath('user.first_name', 'Maria')
            ->assertJsonPath('source', 'account')
            ->assertJsonPath('task', null)
            ->assertJsonPath('image_url', null);

        $this->assertDatabaseHas('reports', ['user_id' => $maria->id, 'review_status' => 'approved', 'status' => 'open']);
    }

    public function test_the_photo_is_shrunk_and_re_encoded(): void
    {
        $this->signIn($this->makeUser());

        $url = $this->post_report($this->reportPayload(['photo' => UploadedFile::fake()->image('big.png', 4000, 3000)]))
            ->assertCreated()->json('image_url');

        $this->assertStringStartsWith('https://res.cloudinary.com/actify-test/image/upload/actify/reports/', $url);
        $path = Report::first()->image_path;
        $this->assertStringStartsWith('cloudinary:actify/reports/', $path);
        [$w, $h, $type] = getimagesizefromstring($this->fakeCloudinary()->bytesForReference($path));
        $this->assertSame(IMAGETYPE_JPEG, $type);
        $this->assertSame(1600, max($w, $h), 'longest side is 1600 px');
        $this->assertEqualsWithDelta(4 / 3, $w / $h, 0.01, 'the shape is kept');
        $this->assertStringNotContainsString('big', $path, 'the stored name is random, not the uploaded one');
    }

    public function test_a_small_photo_is_not_enlarged(): void
    {
        $this->signIn($this->makeUser());
        $this->post_report($this->reportPayload(['photo' => UploadedFile::fake()->image('s.jpg', 400, 300)]))->assertCreated();

        [$w, $h] = getimagesizefromstring($this->fakeCloudinary()->bytesForReference(Report::first()->image_path));
        $this->assertSame([400, 300], [$w, $h]);
    }

    public function test_hidden_data_inside_the_file_is_not_kept(): void
    {
        $this->signIn($this->makeUser());

        $picture = UploadedFile::fake()->image('a.jpg', 50, 50);
        $real = file_get_contents($picture->getPathname());
        $file = UploadedFile::fake()->createWithContent('a.jpg', $real.'<?php system($_GET["x"]); ?>');

        $this->post_report($this->reportPayload(['photo' => $file]))->assertCreated();

        $this->assertStringNotContainsString('<?php', $this->fakeCloudinary()->bytesForReference(Report::first()->image_path));
    }

    public function test_a_cloudinary_upload_failure_does_not_create_a_report_or_claim_success(): void
    {
        $this->signIn($this->makeUser());
        $this->fakeCloudinary()->failUploads = true;

        $this->post_report($this->reportPayload(['photo' => UploadedFile::fake()->image('failed.jpg', 100, 100)]))
            ->assertStatus(500);

        $this->assertDatabaseCount('reports', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_file_that_is_not_a_picture_is_refused_and_nothing_is_created(): void
    {
        $this->signIn($this->makeUser());

        foreach ([
            UploadedFile::fake()->createWithContent('evil.jpg', '<?php echo 1;'),
            UploadedFile::fake()->createWithContent('evil.php', '<?php echo 1;'),
            UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
            UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ] as $file) {
            $this->post_report($this->reportPayload(['photo' => $file]))->assertUnprocessable()->assertJsonValidationErrors('photo');
        }
        $this->assertDatabaseCount('reports', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_photo_over_the_size_limit_is_refused(): void
    {
        $this->signIn($this->makeUser());
        $this->post_report($this->reportPayload(['photo' => UploadedFile::fake()->image('huge.jpg', 100, 100)->size(6000)]))
            ->assertUnprocessable()->assertJsonPath('errors.photo.0', 'The photo is too big (the limit is 5 MB).');
    }

    public function test_a_picture_with_an_absurd_number_of_pixels_is_refused(): void
    {
        config(['actify.images.max_megapixels' => 1]);
        $this->signIn($this->makeUser());

        $this->post_report($this->reportPayload(['photo' => UploadedFile::fake()->image('wide.jpg', 1500, 1500)]))
            ->assertUnprocessable()->assertJsonPath('errors.photo.0', 'That picture is too large. Please use a smaller photo.');
    }

    public function test_required_fields_and_formats(): void
    {
        $this->signIn($this->makeUser());

        $this->post_report([])->assertUnprocessable()->assertJsonValidationErrors(
            ['title', 'description', 'category_id', 'assistance_type', 'urgency', 'barangay', 'latitude', 'longitude'],
        );

        $this->post_report($this->reportPayload(['title' => 'Hi', 'description' => 'too short']))
            ->assertUnprocessable()->assertJsonValidationErrors(['title', 'description']);
        $this->post_report($this->reportPayload(['category_id' => 999]))->assertUnprocessable()->assertJsonValidationErrors('category_id');
        $this->post_report($this->reportPayload(['urgency' => 'extreme', 'assistance_type' => 'magic']))
            ->assertUnprocessable()->assertJsonValidationErrors(['urgency', 'assistance_type']);
        $this->post_report($this->reportPayload(['latitude' => 123, 'longitude' => 'abc']))
            ->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
        $this->post_report($this->reportPayload(['title' => str_repeat('x', 121)]))->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->post_report($this->reportPayload(['description' => str_repeat('x', 2001)]))->assertUnprocessable()->assertJsonValidationErrors('description');
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_the_client_cannot_choose_the_owner_status_or_review_state(): void
    {
        $maria = $this->makeUser();
        $other = $this->makeUser();
        $this->signIn($maria);

        $this->post_report($this->reportPayload([
            'user_id' => $other->id, 'status' => 'resolved', 'review_status' => 'pending', 'source' => 'guest', 'reference' => 'ACT-1',
        ]))->assertCreated();

        $this->assertDatabaseHas('reports', ['user_id' => $maria->id, 'status' => 'open', 'review_status' => 'approved', 'source' => 'account', 'reference' => null]);
    }

    public function test_only_residents_can_file_reports(): void
    {
        $this->post_report($this->reportPayload())->assertUnauthorized();

        foreach ([Role::Volunteer, Role::Admin] as $role) {
            $this->signIn($this->makeUser($role));
            $this->post_report($this->reportPayload())->assertForbidden();
        }
        $this->assertDatabaseCount('reports', 0);
    }
}
