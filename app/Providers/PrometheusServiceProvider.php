<?php

namespace App\Providers;

use App\Models\Transaction;
use App\Models\User;
use App\Services\Monitoring\ApiMetricsCollector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Spatie\Prometheus\Facades\Prometheus;

class PrometheusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $metrics = app(ApiMetricsCollector::class);

        // System Gauges
        Prometheus::addGauge('Memory Usage Bytes')
            ->name('memory_usage_bytes')
            ->helpText('Current PHP memory usage in bytes')
            ->value(fn () => memory_get_usage(true));

        Prometheus::addGauge('Memory Peak Usage Bytes')
            ->name('memory_peak_usage_bytes')
            ->helpText('Peak PHP memory usage in bytes')
            ->value(fn () => memory_get_peak_usage(true));

        Prometheus::addGauge('Database Connection Status')
            ->name('db_status')
            ->helpText('Status of the primary database connection (1 = connected, 0 = disconnected)')
            ->value(function () {
                try {
                    DB::connection()->getPdo();
                    return 1;
                } catch (\Throwable $e) {
                    return 0;
                }
            });

        Prometheus::addGauge('Total Users')
            ->name('users_count')
            ->helpText('Total number of registered users')
            ->value(function () {
                try {
                    return User::count();
                } catch (\Throwable $e) {
                    return 0;
                }
            });

        // Transactions Gauges
        Prometheus::addGauge('Total Transactions')
            ->name('transactions_total')
            ->helpText('Total number of Digiflazz transactions in database')
            ->value(function () {
                try {
                    return Transaction::count();
                } catch (\Throwable $e) {
                    return 0;
                }
            });

        Prometheus::addGauge('Successful Transactions')
            ->name('transactions_success_count')
            ->helpText('Total number of successful Digiflazz transactions')
            ->value(function () {
                try {
                    return Transaction::where('status', 'Sukses')->count();
                } catch (\Throwable $e) {
                    return 0;
                }
            });

        Prometheus::addGauge('Pending Transactions')
            ->name('transactions_pending_count')
            ->helpText('Total number of pending Digiflazz transactions')
            ->value(function () {
                try {
                    return Transaction::where('status', 'Pending')->count();
                } catch (\Throwable $e) {
                    return 0;
                }
            });

        Prometheus::addGauge('Failed Transactions')
            ->name('transactions_failed_count')
            ->helpText('Total number of failed Digiflazz transactions')
            ->value(function () {
                try {
                    return Transaction::where('status', 'Gagal')->count();
                } catch (\Throwable $e) {
                    return 0;
                }
            });

        // API & Operational Metrics
        Prometheus::addGauge('API Requests Total')
            ->name('api_requests_total')
            ->helpText('Total requests served by API endpoints')
            ->value(fn () => $metrics->getTotalRequests());

        Prometheus::addGauge('API Average Latency Ms')
            ->name('api_latency_ms')
            ->helpText('Average API response latency in milliseconds')
            ->value(fn () => $metrics->getAverageLatencyMs());

        Prometheus::addGauge('Auth Logins Success')
            ->name('auth_logins_success_total')
            ->helpText('Total successful user logins')
            ->value(fn () => $metrics->getAuthLogins(true));

        Prometheus::addGauge('Auth Logins Failed')
            ->name('auth_logins_failed_total')
            ->helpText('Total failed user login attempts')
            ->value(fn () => $metrics->getAuthLogins(false));

        Prometheus::addGauge('Inquiry Success Total')
            ->name('inquiry_success_total')
            ->helpText('Total successful PLN inquiries')
            ->value(fn () => $metrics->getInquiriesByStatus('Sukses'));

        Prometheus::addGauge('Inquiry Failed Total')
            ->name('inquiry_failed_total')
            ->helpText('Total failed PLN inquiries')
            ->value(fn () => $metrics->getInquiriesByStatus('Gagal'));

        Prometheus::addGauge('Payment Success Total')
            ->name('payment_success_total')
            ->helpText('Total successful payments')
            ->value(fn () => $metrics->getPaymentsByStatus('Sukses'));

        Prometheus::addGauge('Payment Pending Total')
            ->name('payment_pending_total')
            ->helpText('Total pending payments')
            ->value(fn () => $metrics->getPaymentsByStatus('Pending'));

        Prometheus::addGauge('Payment Failed Total')
            ->name('payment_failed_total')
            ->helpText('Total failed payments')
            ->value(fn () => $metrics->getPaymentsByStatus('Gagal'));
    }
}
