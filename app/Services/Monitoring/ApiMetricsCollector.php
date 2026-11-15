<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Facades\Cache;

class ApiMetricsCollector
{
    private const PREFIX = 'metrics:';

    public function recordApiRequest(string $endpoint, string $method, int $statusCode, float $durationMs): void
    {
        Cache::increment(self::PREFIX . 'requests:total');
        Cache::increment(self::PREFIX . "requests:endpoint:{$endpoint}");
        Cache::increment(self::PREFIX . "requests:status:{$statusCode}");

        // Track rolling latency (moving average sum & count)
        Cache::increment(self::PREFIX . 'latency:count');
        $currentSum = (float) Cache::get(self::PREFIX . 'latency:sum_ms', 0);
        Cache::put(self::PREFIX . 'latency:sum_ms', $currentSum + $durationMs, now()->addDays(7));
    }

    public function recordLoginAttempt(bool $success): void
    {
        $key = $success ? 'auth:login:success' : 'auth:login:failed';
        Cache::increment(self::PREFIX . $key);
    }

    public function recordInquiry(string $status): void
    {
        Cache::increment(self::PREFIX . "inquiry:{$status}");
    }

    public function recordPayment(string $status): void
    {
        Cache::increment(self::PREFIX . "payment:{$status}");
    }

    public function getTotalRequests(): int
    {
        return (int) Cache::get(self::PREFIX . 'requests:total', 0);
    }

    public function getRequestsByEndpoint(string $endpoint): int
    {
        return (int) Cache::get(self::PREFIX . "requests:endpoint:{$endpoint}", 0);
    }

    public function getRequestsByStatus(int $statusCode): int
    {
        return (int) Cache::get(self::PREFIX . "requests:status:{$statusCode}", 0);
    }

    public function getAverageLatencyMs(): float
    {
        $count = (int) Cache::get(self::PREFIX . 'latency:count', 0);
        if ($count === 0) {
            return 0.0;
        }

        $sum = (float) Cache::get(self::PREFIX . 'latency:sum_ms', 0);
        return round($sum / $count, 2);
    }

    public function getAuthLogins(bool $success): int
    {
        $key = $success ? 'auth:login:success' : 'auth:login:failed';
        return (int) Cache::get(self::PREFIX . $key, 0);
    }

    public function getInquiriesByStatus(string $status): int
    {
        return (int) Cache::get(self::PREFIX . "inquiry:{$status}", 0);
    }

    public function getPaymentsByStatus(string $status): int
    {
        return (int) Cache::get(self::PREFIX . "payment:{$status}", 0);
    }
}
