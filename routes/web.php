<?php

use App\Http\Controllers\Api\V1\PlnInquiryController;
use App\Http\Controllers\Api\V1\PlnTransactionController;
use App\Http\Middleware\PrometheusApiMetricsMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Digiflazz direct root aliases matching https://api.digiflazz.com/v1/...
Route::middleware([PrometheusApiMetricsMiddleware::class])->prefix('v1')->group(function () {
    Route::post('inquiry-pln', [PlnInquiryController::class, 'inquire']);
    Route::post('transaction', [PlnTransactionController::class, 'pay']);
    Route::post('payment-pln', [PlnTransactionController::class, 'pay']);
});
