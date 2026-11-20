<?php

namespace Tests;

use App\Enums\Role;
use App\Models\Category;
use App\Models\User;
use App\Support\CloudinaryImageGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeCloudinaryImageGateway;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();


        Storage::fake('public');
        Storage::fake('private');

        $cloudinary = new FakeCloudinaryImageGateway;
        $this->app->instance(CloudinaryImageGateway::class, $cloudinary);
        $this->app->instance(FakeCloudinaryImageGateway::class, $cloudinary);
    }

    protected function fakeCloudinary(): FakeCloudinaryImageGateway
    {
        return $this->app->make(FakeCloudinaryImageGateway::class);
    }


    protected function signIn(User $user): static
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user);

        return $this;
    }


    protected function signOut(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    protected function makeUser(Role $role = Role::Resident, array $attributes = []): User
    {
        return User::factory()->state(['role' => $role])->create($attributes);
    }

    protected function category(): Category
    {
        return Category::query()->first() ?? Category::factory()->create(['name' => 'Flooding']);
    }


    protected function reportPayload(array $override = []): array
    {
        return array_merge([
            'title' => 'Clogged drain on Rizal Street',
            'description' => 'The drain has been blocked for a week and water gathers on the road.',
            'category_id' => $this->category()->id,
            'assistance_type' => 'cleanup',
            'urgency' => 'medium',
            'barangay' => 'Apokon',
            'latitude' => 7.4631,
            'longitude' => 125.8112,
        ], $override);
    }


    protected function guestPayload(array $override = []): array
    {
        return array_merge($this->reportPayload(), [
            'first_name' => 'Gina',
            'last_name' => 'Guest',
            'phone' => '0912 345 6789',
            'email' => 'gina@example.test',
            'reporter_barangay' => 'Magugpo Poblacion',
            'photo' => UploadedFile::fake()->image('problem.jpg', 640, 480),
            'photo_captured_at' => now()->subMinute()->toISOString(),
            'photo_capture_method' => 'live',
            'selfie' => UploadedFile::fake()->image('selfie.jpg', 480, 640),
            'selfie_captured_at' => now()->subMinutes(2)->toISOString(),
            'selfie_capture_method' => 'live',
            'liveness_prompt' => 'Hold up 2 fingers',
            'consent' => '1',
        ], $override);
    }
}
