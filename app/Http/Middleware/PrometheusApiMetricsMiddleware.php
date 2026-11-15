<?php

namespace App\Http\Middleware;

use App\Services\Monitoring\ApiMetricsCollector;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrometheusApiMetricsMiddleware
{
    public function __construct(
        protected ApiMetricsCollector $metricsCollector
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        $response = $next($request);

        $durationMs = (microtime(true) - $start) * 1000;
        $endpoint = $request->route() ? $request->route()->uri() : $request->path();

        $this->metricsCollector->recordApiRequest(
            endpoint: $endpoint,
            method: $request->method(),
            statusCode: $response->getStatusCode(),
            durationMs: $durationMs
        );

        return $response;
    }
}
