<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Stops requests from people whose role is not allowed.
class EnsureRole
{
    // Checks the user's role against the allowed list.
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Please sign in first.'], 401);
        }

        if (! in_array($user->role->value, $roles, true)) {
            return response()->json(['message' => 'You are not allowed to do that.'], 403);
        }

        return $next($request);
    }
}
