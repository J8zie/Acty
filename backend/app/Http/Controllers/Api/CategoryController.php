<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

// Sends back the list of report categories.
class CategoryController extends Controller
{

    // Returns every category's id and name.
    public function __invoke(): JsonResponse
    {
        return response()->json(Category::query()->orderBy('id')->get(['id', 'name']));
    }
}
