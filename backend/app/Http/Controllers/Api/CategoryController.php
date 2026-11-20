<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

// Sends back the list of report categories.
class CategoryController extends Controller
{
    // Returns every category's id and name, cached because the list rarely changes.
    public function __invoke(): JsonResponse
    {
        $categories = Cache::remember('actify.categories', 3600, fn () => Category::query()->orderBy('id')->get(['id', 'name']));

        return response()->json($categories);
    }
}
