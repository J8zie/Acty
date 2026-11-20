<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\GuestReportController;
use App\Http\Controllers\Api\ImageController;
use App\Http\Controllers\Api\RankingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReportReviewController;
use App\Http\Controllers\Api\ReportWorkflowController;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Support\Facades\Route;

// Everything the Angular app can call, under /api.
// Three kinds of route: public (no token), auth:sanctum (login token) and role:xxx (that role too).

// {report} and {user} in an address are always whole numbers (anything else is a plain 404).
Route::pattern('report', '[0-9]+');
Route::pattern('user', '[0-9]+');

// A tiny health check anyone can call.
Route::get('/', fn () => response()->json(['name' => config('app.name'), 'status' => 'ok']));

// ---- Sign up / sign in / my account ----
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AccountController::class, 'show']);
        Route::patch('me', [AccountController::class, 'update']);
        Route::post('me/photo', [AccountController::class, 'storePhoto'])->middleware('throttle:uploads');
        Route::delete('me/photo', [AccountController::class, 'destroyPhoto']);
        Route::put('me/password', [AccountController::class, 'updatePassword'])->middleware('throttle:password');
    });
});

// ---- Public ----
Route::get('categories', CategoryController::class);
Route::get('stats', StatsController::class);
Route::get('volunteers/rankings', RankingController::class);

Route::get('reports', [ReportController::class, 'index']);
Route::post('reports/guest', [GuestReportController::class, 'store'])->middleware('throttle:guest-reports');
Route::get('reports/{report}', [ReportController::class, 'show']);

// A private picture. No token (an <img> tag cannot send one): the signed, short-lived address is the key.
Route::get('reports/{report}/private/{kind}', [ImageController::class, 'private'])

    ->whereIn('kind', ['photo', 'selfie', 'video'])
    ->middleware('signed:relative')
    ->name('reports.private');

// ---- Signed in ----
Route::middleware('auth:sanctum')->group(function () {
    // resident
    Route::post('reports', [ReportController::class, 'store'])->middleware(['role:resident', 'throttle:uploads']);
    Route::post('reports/{report}/rating', [ReportWorkflowController::class, 'rating'])
        ->middleware('role:resident');

    // volunteer
    Route::middleware('role:volunteer')->group(function () {
        Route::post('reports/{report}/accept', [ReportWorkflowController::class, 'accept']);
        Route::post('reports/{report}/start', [ReportWorkflowController::class, 'start']);
        Route::post('reports/{report}/complete', [ReportWorkflowController::class, 'complete'])->middleware('throttle:uploads');
    });

    // admin
    Route::middleware('role:admin')->group(function () {
        Route::patch('reports/{report}', [ReportController::class, 'update']);
        Route::delete('reports/{report}', [ReportController::class, 'destroy']);
        Route::post('reports/{report}/review', ReportReviewController::class);

        Route::prefix('admin')->group(function () {
            Route::get('users', [AdminUserController::class, 'index']);
            Route::patch('users/{user}', [AdminUserController::class, 'update']);
            Route::delete('users/{user}', [AdminUserController::class, 'destroy']);
        });
    });
});
