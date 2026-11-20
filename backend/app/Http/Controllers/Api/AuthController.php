<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /** A real bcrypt hash of a random word. Checked when the username does not exist, so "no such user" takes as long as "wrong password". */
    private const DUMMY_HASH = '$2y$12$5hSj.gnoIP1bO1tssPFR5.tHP7uL5nvv8faXZ11Cj4SI6MiBXNHOy';

    /** POST /auth/register -> 201 { token, user } */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'username' => $data['username'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
        ]);

        return response()->json([
            'token' => $this->issueToken($user),
            'user' => new UserResource($user),
        ], 201);
    }

    /** POST /auth/login -> 200 { token, user }. Wrong details: 422 with a message (the sign-in page shows it). */
    public function login(LoginRequest $request): JsonResponse
    {
        $login = Str::lower(trim($request->string('username')->toString()));

        $user = User::query()
            ->where('username', $login)
            ->orWhere('email', $login)
            ->first();

        // Same message for "no such user" and "wrong password": never tell a stranger which usernames exist.
        $ok = Hash::check($request->string('password')->toString(), $user?->password ?? self::DUMMY_HASH) && $user;

        if (! $ok) {
            throw ValidationException::withMessages(['username' => ['Wrong username or password.']]);
        }

        return response()->json([
            'token' => $this->issueToken($user),
            'user' => new UserResource($user),
        ]);
    }

    /** POST /auth/logout -> 204. Only THIS device's token is deleted. */
    public function logout(Request $request): Response
    {
        $token = $request->user()->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }

    /** A new token for this sign-in. A person keeps at most 10 devices signed in; the oldest are dropped. */
    private function issueToken(User $user): string
    {
        $old = $user->tokens()->latest('id')->skip(9)->take(1000)->pluck('id');
        if ($old->isNotEmpty()) {
            $user->tokens()->whereIn('id', $old)->delete();
        }

        return $user->createToken('actify')->plainTextToken;
    }
}
