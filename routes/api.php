<?php

use App\Http\Controllers\ArticlesController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserPreferenceController;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);


Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::post('/password/email', [AuthController::class, 'sendPasswordResetLink'])->middleware('throttle:password-email');
Route::post('/password/reset', [AuthController::class, 'resetPassword'])
    ->name('password.update')
    ->middleware('throttle:password-reset');
Route::get('/password/reset', function () {
    return view('reset-password');
})->name('password.reset')->middleware('throttle:password-reset-view');
Route::post('/email/verification/resend', [AuthController::class, 'resendVerificationEmail'])
    ->name('verification.resend')
    ->middleware('throttle:verification-resend');

Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify.api')
    ->middleware('throttle:verification-verify');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/articles', [ArticlesController::class, 'index']);
    Route::get('/article', [ArticlesController::class, 'show']);

    // User Preferences Routes
    Route::post('/preferences', [UserPreferenceController::class, 'store']);
    Route::get('/preferences', [UserPreferenceController::class, 'show']);
    Route::get('/feed', [UserPreferenceController::class, 'feed']);
});