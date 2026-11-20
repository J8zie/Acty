<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /** GET /categories -> [{ id, name }] */
    public function __invoke(): JsonResponse
    {
        return response()->json(Category::query()->orderBy('id')->get(['id', 'name']));
    }
}
