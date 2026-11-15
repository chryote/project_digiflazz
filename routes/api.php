<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MockWebhookController;
use App\Http\Controllers\Api\V1\PlnInquiryController;
use App\Http\Controllers\Api\V1\PlnTransactionController;
use App\Http\Middleware\PrometheusApiMetricsMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware([PrometheusApiMetricsMiddleware::class])->group(function () {
    // API Version 1
    Route::prefix('v1')->group(function () {
        // Authentication routes (Rate-limited to protect against brute-force attacks)
        Route::prefix('auth')->group(function () {
            Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

            Route::middleware('auth:sanctum')->group(function () {
                Route::get('me', [AuthController::class, 'me']);
                Route::post('logout', [AuthController::class, 'logout']);
                Route::post('regenerate-api-key', [AuthController::class, 'regenerateApiKey']);
            });
        });

        // Digiflazz PLN API Spec Endpoints
        Route::post('inquiry-pln', [PlnInquiryController::class, 'inquire']);
        Route::post('transaction', [PlnTransactionController::class, 'pay']);
        Route::post('payment-pln', [PlnTransactionController::class, 'pay']);

        // Mock Webhook Receiver for testing callbacks
        Route::post('mock-webhook', [MockWebhookController::class, 'handle']);
    });
});
