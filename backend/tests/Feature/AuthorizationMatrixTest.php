<?php

// Tests for Authorization Matrix Test.

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Report;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthorizationMatrixTest extends TestCase
{
    public static function matrix(): array
    {
        return [

            'me' => ['GET', '/api/auth/me', 401, 200, 200, 200],
            'update me' => ['PATCH', '/api/auth/me', 401, 422, 422, 422],
            'logout' => ['POST', '/api/auth/logout', 401, 204, 204, 204],
            'password' => ['PUT', '/api/auth/me/password', 401, 422, 422, 422],
            'photo' => ['POST', '/api/auth/me/photo', 401, 422, 422, 422],
            'remove photo' => ['DELETE', '/api/auth/me/photo', 401, 200, 200, 200],

            'new report' => ['POST', '/api/reports', 401, 422, 403, 403],
            'accept' => ['POST', '/api/reports/{open}/accept', 401, 403, 200, 403],
            'start' => ['POST', '/api/reports/{open}/start', 401, 403, 403, 403],
            'complete' => ['POST', '/api/reports/{open}/complete', 401, 403, 403, 403],
            'rating' => ['POST', '/api/reports/{open}/rating', 401, 403, 403, 403],
            'set status' => ['PATCH', '/api/reports/{open}', 401, 403, 403, 422],
            'delete report' => ['DELETE', '/api/reports/{open}', 401, 403, 403, 422],
            'review' => ['POST', '/api/reports/{open}/review', 401, 403, 403, 422],

            'my reports' => ['GET', '/api/reports?scope=mine', 401, 200, 200, 200],
            'my tasks' => ['GET', '/api/reports?scope=tasks', 401, 200, 200, 200],
            'review queue' => ['GET', '/api/reports?review=pending', 401, 403, 403, 200],

            'people' => ['GET', '/api/admin/users', 401, 403, 403, 200],
            'change role' => ['PATCH', '/api/admin/users/{other}', 401, 403, 403, 422],
            'delete person' => ['DELETE', '/api/admin/users/{other}', 401, 403, 403, 422],


            'list' => ['GET', '/api/reports', 200, 200, 200, 200],
            'one report' => ['GET', '/api/reports/{open}', 200, 200, 200, 200],
            'categories' => ['GET', '/api/categories', 200, 200, 200, 200],
            'stats' => ['GET', '/api/stats', 200, 200, 200, 200],
            'rankings' => ['GET', '/api/volunteers/rankings', 200, 200, 200, 200],
        ];
    }

    #[DataProvider('matrix')]
    public function test_who_may_call_what(string $method, string $uri, int $visitor, int $resident, int $volunteer, int $admin): void
    {
        $owner = $this->makeUser(Role::Resident);
        $other = $this->makeUser(Role::Resident);
        $open = Report::factory()->create(['user_id' => $owner->id, 'category_id' => $this->category()->id]);
        $uri = str_replace(['{open}', '{other}'], [$open->id, $other->id], $uri);

        $callers = [
            'visitor' => [null, $visitor],
            'resident' => [$this->makeUser(Role::Resident), $resident],
            'volunteer' => [$this->makeUser(Role::Volunteer), $volunteer],
            'admin' => [$this->makeUser(Role::Admin), $admin],
        ];

        foreach ($callers as $name => [$user, $expected]) {

            Report::query()->update(['status' => 'open']);
            \App\Models\ReportTask::query()->delete();

            $user ? $this->signIn($user) : $this->signOut();
            $response = $this->json($method, $uri);

            $this->assertSame($expected, $response->status(), "$name -> $method $uri expected $expected, got {$response->status()}: ".$response->getContent());
        }
    }
}
