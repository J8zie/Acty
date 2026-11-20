<?php

namespace App\Http\Controllers;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * `throw $this->error('This request was already taken.', 409);` answers { "message": "..." } with that status.
     * (Throwing it inside a database transaction rolls the transaction back first.)
     */
    protected function error(string $message, int $status): HttpResponseException
    {
        return new HttpResponseException(response()->json(['message' => $message], $status));
    }

    /**
     * Paging info for list endpoints. The body stays a plain array (the Angular app expects that); the totals
     * travel in headers: X-Total-Count, X-Page, X-Per-Page.
     *
     * @return array{0:int,1:int} [limit, page]
     */
    protected function paging(Request $request): array
    {
        $limit = (int) ($request->query('limit') ?: config('actify.pagination.default'));
        $page = max(1, (int) $request->query('page', 1));

        return [$limit, $page];
    }

    protected function pagingHeaders(int $total, int $page, int $limit): array
    {
        return ['X-Total-Count' => $total, 'X-Page' => $page, 'X-Per-Page' => $limit];
    }
}
