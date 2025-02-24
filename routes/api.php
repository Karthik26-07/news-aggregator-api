<?php

use App\Http\Controllers\ArticlesController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserPreferenceController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);

Route::middleware('throttle:5,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::post('/password/email', [AuthController::class, 'sendPasswordResetLink']);
    Route::post('/password/reset', action: [AuthController::class, 'resetPassword'])->name('password.update');
    Route::get('/password/reset', function () {
        return view('reset-password'); // Make sure this file exists in resources/views/auth/
    })->name('password.reset');

    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify.api');
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/articles', [ArticlesController::class, 'index']);
    Route::get('/article', [ArticlesController::class, 'show']);

    // User Preferences Routes
    Route::post('/preferences', [UserPreferenceController::class, 'store']);
    Route::get('/preferences', [UserPreferenceController::class, 'show']);
    Route::get('/feed', [UserPreferenceController::class, 'feed']);
});