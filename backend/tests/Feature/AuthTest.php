<?php

// Tests for Auth Test.

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthTest extends TestCase
{
    private function registerBody(array $override = []): array
    {
        return array_merge([
            'username' => 'maria_s',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria@example.test',
            'password' => 'secret123',
            'role' => 'resident',
        ], $override);
    }

    public function test_register_returns_a_token_and_the_user(): void
    {
        $response = $this->postJson('/api/auth/register', $this->registerBody())->assertCreated();

        $response->assertJsonStructure(['token', 'user' => ['id', 'username', 'first_name', 'last_name', 'email', 'role', 'created_at', 'photo_url']])
            ->assertJsonPath('user.role', 'resident')
            ->assertJsonPath('user.photo_url', null)
            ->assertJsonMissingPath('user.password');
        $this->assertDatabaseHas('users', ['username' => 'maria_s', 'role' => 'resident']);

        $this->assertNotSame('secret123', User::first()->password);
    }

    public function test_the_token_from_register_works(): void
    {
        $token = $this->postJson('/api/auth/register', $this->registerBody())->json('token');

        $this->signOut();
        $this->withToken($token)->getJson('/api/auth/me')->assertOk()->assertJsonPath('username', 'maria_s');
    }

    public function test_a_volunteer_can_register_but_an_admin_cannot(): void
    {
        $this->postJson('/api/auth/register', $this->registerBody(['role' => 'volunteer']))->assertCreated();
        $this->postJson('/api/auth/register', $this->registerBody(['username' => 'evil', 'email' => 'e@example.test', 'role' => 'admin']))
            ->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertDatabaseMissing('users', ['username' => 'evil']);
    }

    public function test_usernames_and_emails_are_stored_in_lowercase_and_unique_ignoring_case(): void
    {
        $this->postJson('/api/auth/register', $this->registerBody(['username' => 'Maria_S', 'email' => 'MARIA@Example.Test']))->assertCreated();
        $this->assertDatabaseHas('users', ['username' => 'maria_s', 'email' => 'maria@example.test']);

        $this->postJson('/api/auth/register', $this->registerBody(['username' => 'MARIA_S', 'email' => 'other@example.test']))
            ->assertUnprocessable()->assertJsonValidationErrors('username');
        $this->postJson('/api/auth/register', $this->registerBody(['username' => 'someone', 'email' => 'MARIA@example.test']))
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_register_validation_messages(): void
    {
        $bad = [
            'username' => 'a b!',
            'first_name' => 'M4ria',
            'last_name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'role' => 'wizard',
        ];

        $this->postJson('/api/auth/register', $bad)->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'first_name', 'last_name', 'email', 'password', 'role']);
    }

    public function test_passwords_need_eight_characters_a_letter_and_a_number(): void
    {
        foreach (['abc12' => 'short', 'abcdefgh' => 'no number', '12345678' => 'no letter', str_repeat('a1', 40) => 'too long (80)'] as $password => $why) {
            $this->postJson('/api/auth/register', $this->registerBody(['password' => (string) $password]))
                ->assertUnprocessable($why)->assertJsonValidationErrors('password');
        }
        $this->postJson('/api/auth/register', $this->registerBody(['password' => 'abcd1234']))->assertCreated();
    }

    public function test_names_with_accents_apostrophes_and_hyphens_are_fine(): void
    {
        $this->postJson('/api/auth/register', $this->registerBody(['first_name' => 'Anne-Marie', 'last_name' => "O'Brien Peña"]))->assertCreated();
    }

    public function test_login_with_username_or_email(): void
    {
        $user = $this->makeUser(Role::Volunteer, ['username' => 'juan', 'email' => 'juan@example.test', 'password' => 'pass1234']);

        $this->postJson('/api/auth/login', ['username' => 'juan', 'password' => 'pass1234'])
            ->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonStructure(['token']);
        $this->postJson('/api/auth/login', ['username' => 'JUAN@example.test', 'password' => 'pass1234'])
            ->assertOk()->assertJsonPath('user.username', 'juan');
    }

    public function test_wrong_credentials_get_one_message_that_does_not_reveal_which_part_was_wrong(): void
    {
        $this->makeUser(Role::Resident, ['username' => 'maria', 'password' => 'pass1234']);

        $wrongPassword = $this->postJson('/api/auth/login', ['username' => 'maria', 'password' => 'nope1234']);
        $unknownUser = $this->postJson('/api/auth/login', ['username' => 'ghost', 'password' => 'pass1234']);

        $wrongPassword->assertUnprocessable()->assertJsonPath('message', 'Wrong username or password.');
        $unknownUser->assertUnprocessable()->assertJsonPath('message', 'Wrong username or password.');
        $this->assertSame($wrongPassword->json(), $unknownUser->json());
    }

    public function test_login_requires_both_fields(): void
    {
        $this->postJson('/api/auth/login', [])->assertUnprocessable()->assertJsonValidationErrors(['username', 'password']);
    }

    public function test_the_sixth_login_attempt_in_a_minute_is_throttled(): void
    {
        $this->makeUser(Role::Resident, ['username' => 'maria']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['username' => 'maria', 'password' => 'wrong-'.$i])->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', ['username' => 'maria', 'password' => 'pass1234'])
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('message', 'Too many tries from this connection. Please wait a moment and try again.');
    }

    public function test_register_is_limited_per_address(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/register', $this->registerBody(['username' => "user_$i", 'email' => "u$i@example.test"]))->assertCreated();
        }
        $this->postJson('/api/auth/register', $this->registerBody(['username' => 'user_x', 'email' => 'ux@example.test']))->assertStatus(429);
    }

    public function test_me_needs_a_token(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized()->assertJsonStructure(['message']);
    }

    public function test_logout_deletes_only_this_devices_token(): void
    {
        $user = $this->makeUser();
        $phone = $user->createToken('phone')->plainTextToken;
        $laptop = $user->createToken('laptop')->plainTextToken;

        $this->withToken($phone)->postJson('/api/auth/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->signOut();
        $this->withToken($phone)->getJson('/api/auth/me')->assertUnauthorized();
        $this->signOut();
        $this->withToken($laptop)->getJson('/api/auth/me')->assertOk();
    }

    public function test_an_expired_token_is_refused(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('old')->plainTextToken;

        $this->travel(31)->days();

        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_only_ten_devices_stay_signed_in(): void
    {
        $user = $this->makeUser(Role::Resident, ['username' => 'maria', 'password' => 'pass1234']);
        for ($i = 0; $i < 12; $i++) {
            $user->createToken("device $i");
        }

        $this->postJson('/api/auth/login', ['username' => 'maria', 'password' => 'pass1234'])->assertOk();

        $this->assertSame(10, $user->tokens()->count());
    }



    public function test_update_profile_name(): void
    {
        $this->signIn($user = $this->makeUser(Role::Resident, ['email' => 'maria@example.test']));

        $this->patchJson('/api/auth/me', ['first_name' => 'Mia', 'last_name' => 'Santos', 'email' => 'maria@example.test'])
            ->assertOk()->assertJsonPath('first_name', 'Mia');
        $this->assertSame('Mia', $user->fresh()->first_name);
    }

    public function test_changing_the_email_needs_the_current_password(): void
    {
        $this->signIn($user = $this->makeUser(Role::Resident, ['email' => 'maria@example.test', 'password' => 'pass1234']));
        $body = ['first_name' => 'Maria', 'last_name' => 'Santos', 'email' => 'new@example.test'];

        $this->patchJson('/api/auth/me', $body)
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->patchJson('/api/auth/me', $body + ['current_password' => 'wrong-one1'])
            ->assertUnprocessable()->assertJsonPath('errors.current_password.0', 'Your current password is not right.');
        $this->assertSame('maria@example.test', $user->fresh()->email);

        $this->patchJson('/api/auth/me', $body + ['current_password' => 'pass1234'])->assertOk()->assertJsonPath('email', 'new@example.test');
    }

    public function test_the_email_cannot_be_one_that_belongs_to_someone_else(): void
    {
        $this->makeUser(Role::Resident, ['email' => 'taken@example.test']);
        $this->signIn($this->makeUser(Role::Resident, ['password' => 'pass1234']));

        $this->patchJson('/api/auth/me', ['first_name' => 'A', 'last_name' => 'B', 'email' => 'taken@example.test', 'current_password' => 'pass1234'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_profile_photo_is_re_encoded_as_a_512_pixel_square_jpeg_and_replaced(): void
    {
        $this->signIn($user = $this->makeUser());

        $first = $this->post('/api/auth/me/photo', ['photo' => UploadedFile::fake()->image('big.png', 2000, 1000)], ['Accept' => 'application/json'])
            ->assertOk()->json('photo_url');
        $this->assertStringStartsWith('https://res.cloudinary.com/actify-test/image/upload/actify/avatars/', $first);

        $path = $user->fresh()->photo_path;
        [$w, $h, $type] = getimagesizefromstring($this->fakeCloudinary()->bytesForReference($path));
        $this->assertSame([512, 512, IMAGETYPE_JPEG], [$w, $h, $type]);


        $second = $this->post('/api/auth/me/photo', ['photo' => UploadedFile::fake()->image('two.jpg', 300, 300)], ['Accept' => 'application/json'])
            ->assertOk()->json('photo_url');
        $this->assertFalse($this->fakeCloudinary()->existsForReference($path));
        $this->assertNotSame($first, $second);
    }

    public function test_a_failed_avatar_replacement_keeps_the_existing_photo(): void
    {
        $this->signIn($user = $this->makeUser());
        $this->post('/api/auth/me/photo', ['photo' => UploadedFile::fake()->image('first.jpg', 100, 100)], ['Accept' => 'application/json'])->assertOk();
        $oldPath = $user->fresh()->photo_path;
        $this->fakeCloudinary()->failUploads = true;

        $this->post('/api/auth/me/photo', ['photo' => UploadedFile::fake()->image('replacement.jpg', 100, 100)], ['Accept' => 'application/json'])
            ->assertStatus(500);

        $this->assertSame($oldPath, $user->fresh()->photo_path);
        $this->assertTrue($this->fakeCloudinary()->existsForReference($oldPath));
    }

    public function test_a_database_failure_during_photo_replacement_keeps_the_old_asset(): void
    {
        $this->signIn($user = $this->makeUser());
        $this->post('/api/auth/me/photo', ['photo' => UploadedFile::fake()->image('first.jpg', 100, 100)], ['Accept' => 'application/json'])->assertOk();
        $oldPath = $user->fresh()->photo_path;
        User::saving(function (User $candidate) use ($user): ?bool {
            return $candidate->is($user) && $candidate->isDirty('photo_path') ? false : null;
        });

        $this->post('/api/auth/me/photo', ['photo' => UploadedFile::fake()->image('replacement.jpg', 100, 100)], ['Accept' => 'application/json'])
            ->assertStatus(500);

        $this->assertSame($oldPath, $user->fresh()->photo_path);
        $this->assertTrue($this->fakeCloudinary()->existsForReference($oldPath));
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_a_file_that_is_not_a_picture_is_refused(): void
    {
        $this->signIn($this->makeUser());

        $this->post('/api/auth/me/photo', ['photo' => UploadedFile::fake()->createWithContent('x.jpg', '<?php echo 1;')], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
        $this->post('/api/auth/me/photo', [], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonPath('errors.photo.0', 'Choose a picture first.');
    }

    public function test_a_corrupt_picture_with_a_jpeg_header_is_refused(): void
    {
        $this->signIn($this->makeUser());

        $fake = UploadedFile::fake()->createWithContent('broken.jpg', "\xFF\xD8\xFF\xE0".str_repeat("\x00", 200));

        $this->post('/api/auth/me/photo', ['photo' => $fake], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('photo');
    }

    public function test_remove_the_photo(): void
    {
        $this->signIn($user = $this->makeUser());
        $this->post('/api/auth/me/photo', ['photo' => UploadedFile::fake()->image('a.jpg', 100, 100)], ['Accept' => 'application/json'])->assertOk();
        $path = $user->fresh()->photo_path;

        $this->deleteJson('/api/auth/me/photo')->assertOk()->assertJsonPath('photo_url', null);
        $this->assertFalse($this->fakeCloudinary()->existsForReference($path));
    }

    public function test_change_password(): void
    {
        $user = $this->makeUser(Role::Resident, ['password' => 'pass1234']);
        $other = $user->createToken('other device')->plainTextToken;
        $this->signIn($user);

        $this->putJson('/api/auth/me/password', ['current_password' => 'wrong-one1', 'password' => 'newpass99', 'password_confirmation' => 'newpass99'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->putJson('/api/auth/me/password', ['current_password' => 'pass1234', 'password' => 'newpass99', 'password_confirmation' => 'different9'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->putJson('/api/auth/me/password', ['current_password' => 'pass1234', 'password' => 'pass1234', 'password_confirmation' => 'pass1234'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->putJson('/api/auth/me/password', ['current_password' => 'pass1234', 'password' => 'weak', 'password_confirmation' => 'weak'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->putJson('/api/auth/me/password', ['current_password' => 'pass1234', 'password' => 'newpass99', 'password_confirmation' => 'newpass99'])
            ->assertNoContent();


        $this->signOut();
        $this->postJson('/api/auth/login', ['username' => $user->username, 'password' => 'newpass99'])->assertOk();
        $this->postJson('/api/auth/login', ['username' => $user->username, 'password' => 'pass1234'])->assertUnprocessable();
        $this->signOut();
        $this->withToken($other)->getJson('/api/auth/me')->assertUnauthorized();
    }
}
