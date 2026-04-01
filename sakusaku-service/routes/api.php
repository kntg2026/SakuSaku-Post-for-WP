<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Auth\LogoutController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'resolve-tenant', 'tenant-active'])->group(function () {
    Route::post('/auth/logout', [LogoutController::class, 'logout']);

    Route::get('/me', function (Request $request) {
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'level' => $user->level->value,
            'avatar_url' => $user->avatar_url,
            'tenant' => [
                'id' => $user->tenant->id,
                'name' => $user->tenant->name,
                'slug' => $user->tenant->slug,
            ],
        ]);
    });

    // Poster API
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/posts', [PostController::class, 'index']);
    Route::post('/posts', [PostController::class, 'store']);
    Route::get('/posts/{post}', [PostController::class, 'show']);
    Route::delete('/posts/{post}', [PostController::class, 'destroy']);
    Route::post('/posts/{post}/publish', [PostController::class, 'publish'])
        ->middleware('user-level:2');

    // Admin API (Step 8)
    Route::prefix('admin')->middleware('admin-role')->group(function () {
        // Will be populated in Step 8
    });

    // Billing (Step 10)
    Route::prefix('billing')->middleware('admin-role')->group(function () {
        // Will be populated in Step 10
    });
});
