<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes every /api request behave as if it had sent `Accept: application/json`.
 *
 * Without it, a form error from a caller that forgot the header (curl, Postman, a browser tab) would be an HTML
 * redirect instead of the JSON the Angular app expects.
 */
class ForceJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
