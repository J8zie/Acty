<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `->middleware('role:admin')` or `->middleware('role:resident,volunteer')`.
 *
 * Runs after `auth:sanctum`. The Angular app already hides what a role may not do, but hiding a button is not
 * security: this is the check that really keeps a volunteer out of an admin route.
 */
class EnsureRole
{
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
