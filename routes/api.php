<?php

declare(strict_types=1);

use App\Http\Controllers\ApiAuthController;
use App\Http\Controllers\CallLogController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\ReminderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [ApiAuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', function (Request $request) {
        return $request->user();
    });

    Route::get('/leads', [LeadController::class, 'assigned']);

    Route::post('/call-logs', [CallLogController::class, 'store']);
    Route::get('/call-logs', [CallLogController::class, 'index']);

    Route::post('/reminders', [ReminderController::class, 'store']);
    Route::get('/reminders', [ReminderController::class, 'index']);
});
