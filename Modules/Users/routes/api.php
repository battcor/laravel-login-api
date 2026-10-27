<?php

use Illuminate\Support\Facades\Route;
use Modules\Users\Http\Controllers\ProfileController;

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::delete('/profile', [ProfileController::class, 'destroy']);
});
