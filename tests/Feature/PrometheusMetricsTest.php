<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrometheusMetricsTest extends TestCase
{
    public function test_metrics_endpoint_returns_prometheus_format(): void
    {
        $response = $this->get('/metrics');

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('laravel_db_status', $content);
        $this->assertStringContainsString('laravel_memory_usage_bytes', $content);
        $this->assertStringContainsString('laravel_api_requests_total', $content);
        $this->assertStringContainsString('laravel_transactions_total', $content);
    }
}
