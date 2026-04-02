<?php

use App\Http\Controllers\Admin\CategoryManagementController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NotificationSettingsController;
use App\Http\Controllers\Admin\PostManagementController;
use App\Http\Controllers\Admin\TenantSettingsController;
use App\Http\Controllers\Admin\UserManagementController;
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
        Route::get('/dashboard', DashboardController::class);

        // Post management
        Route::get('/posts', [PostManagementController::class, 'index']);
        Route::get('/posts/{post}', [PostManagementController::class, 'show']);
        Route::post('/posts/{post}/approve', [PostManagementController::class, 'approve']);
        Route::post('/posts/{post}/publish', [PostManagementController::class, 'publish']);
        Route::post('/posts/{post}/reject', [PostManagementController::class, 'reject']);
        Route::put('/posts/{post}/category', [PostManagementController::class, 'updateCategory']);
        Route::delete('/posts/{post}', [PostManagementController::class, 'destroy']);

        // User management
        Route::get('/users', [UserManagementController::class, 'index']);
        Route::put('/users/{user}/level', [UserManagementController::class, 'updateLevel']);
        Route::put('/users/{user}/role', [UserManagementController::class, 'updateRole']);

        // Category management
        Route::get('/categories', [CategoryManagementController::class, 'index']);
        Route::post('/categories', [CategoryManagementController::class, 'store']);
        Route::put('/categories/{category}', [CategoryManagementController::class, 'update']);
        Route::delete('/categories/{category}', [CategoryManagementController::class, 'destroy']);
        Route::post('/categories/sync', [CategoryManagementController::class, 'syncFromWp']);

        // Notification settings
        Route::get('/notifications', [NotificationSettingsController::class, 'show']);
        Route::put('/notifications', [NotificationSettingsController::class, 'update']);
        Route::post('/notifications/test', [NotificationSettingsController::class, 'test']);

        // Tenant settings
        Route::get('/settings', [TenantSettingsController::class, 'show']);
        Route::put('/settings', [TenantSettingsController::class, 'update']);
        Route::post('/settings/test-wp', [TenantSettingsController::class, 'testWpConnection']);
    });

    // Billing
    Route::prefix('billing')->middleware('admin-role')->group(function () {
        Route::get('/status', [\App\Http\Controllers\Billing\SubscriptionController::class, 'status']);
        Route::post('/checkout', [\App\Http\Controllers\Billing\SubscriptionController::class, 'checkout']);
        Route::post('/portal', [\App\Http\Controllers\Billing\SubscriptionController::class, 'portal']);
    });
});
