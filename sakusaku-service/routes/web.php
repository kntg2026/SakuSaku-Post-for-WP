<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
    ->name('auth.google.redirect');

Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->name('auth.google.callback');

// SPA catch-all (must be last)
Route::get('/{any}', function () {
    return view('welcome'); // Will be replaced with app.blade.php in Step 7
})->where('any', '.*');
