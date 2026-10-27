<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Controllers\AuthController;

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
            ->middleware('throttle:6,1');
        Route::put('/password', [AuthController::class, 'changePassword']);
    });
});
