<?php

namespace App\Http\Controllers;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

// The base class every API controller shares.
abstract class Controller
{
    // Sends back a JSON error with the given message and status code.
    protected function error(string $message, int $status): HttpResponseException
    {
        return new HttpResponseException(response()->json(['message' => $message], $status));
    }

    // Reads the page number and page size from the request.
    protected function paging(Request $request): array
    {
        $limit = (int) ($request->query('limit') ?: config('actify.pagination.default'));
        $page = max(1, (int) $request->query('page', 1));

        return [$limit, $page];
    }

    // Builds the paging headers for a list answer.
    protected function pagingHeaders(int $total, int $page, int $limit): array
    {
        return ['X-Total-Count' => $total, 'X-Page' => $page, 'X-Per-Page' => $limit];
    }
}
