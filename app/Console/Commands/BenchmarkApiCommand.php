<?php

namespace App\Console\Commands;

use App\Constants\DigiflazzConstant;
use App\Models\User;
use Database\Seeders\PlnCustomerSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class BenchmarkApiCommand extends Command
{
    protected $signature = 'digiflazz:benchmark 
                            {--requests=100 : Number of benchmark requests to run per scenario} 
                            {--target=internal : Target to benchmark: internal (in-app kernel) or http (external URL)}
                            {--base-url=http://127.0.0.1:8080 : Base URL if benchmarking via HTTP}';

    protected $description = 'Benchmark the Digiflazz and Auth APIs for latency, RPS throughput, and memory consumption';

    public function handle(): int
    {
        $this->info('===========================================================');
        $this->info('        DIGIFLAZZ API BENCHMARK & PERFORMANCE SUITE        ');
        $this->info('===========================================================');

        $requestsCount = (int) $this->option('requests');
        $target = $this->option('target');
        $baseUrl = rtrim($this->option('base-url'), '/');

        $this->comment("Target Mode: {$target} | Requests per test: {$requestsCount}");
        $this->newLine();

        // Ensure database is seeded
        $this->callSilently('db:seed', ['--class' => UserSeeder::class]);
        $this->callSilently('db:seed', ['--class' => PlnCustomerSeeder::class]);

        $username = DigiflazzConstant::username();
        $user = User::where('username', $username)->first() ?? User::firstOrFail();

        $scenarios = [
            '1. Auth Login API' => function () use ($user) {
                return [
                    'url' => '/api/v1/auth/login',
                    'method' => 'POST',
                    'data' => [
                        'username' => $user->username,
                        'password' => 'password123',
                    ],
                ];
            },
            '2. PLN Inquiry (1 Tagihan)' => function () use ($user) {
                $customerNo = '530000000001';
                $sign = md5($user->username . $user->api_key . $customerNo);
                return [
                    'url' => '/api/v1/inquiry-pln',
                    'method' => 'POST',
                    'data' => [
                        'username' => $user->username,
                        'customer_no' => $customerNo,
                        'sign' => $sign,
                        'ref_id' => 'BENCH-INQ-' . uniqid(),
                    ],
                ];
            },
            '3. PLN Transaction (Payment)' => function () use ($user) {
                $customerNo = '530000000001';
                $refId = 'BENCH-PAY-' . uniqid() . '-' . mt_rand(1000, 9999);
                $sign = md5($user->username . $user->api_key . $refId);
                return [
                    'url' => '/api/v1/transaction',
                    'method' => 'POST',
                    'data' => [
                        'commands' => 'pay-pasca',
                        'username' => $user->username,
                        'buyer_sku_code' => 'pln',
                        'customer_no' => $customerNo,
                        'ref_id' => $refId,
                        'sign' => $sign,
                        'testing' => true,
                    ],
                ];
            },
        ];

        $results = [];

        foreach ($scenarios as $name => $payloadGenerator) {
            $this->output->write("Running benchmark: <comment>{$name}</comment> ... ");
            $latencies = [];
            $successes = 0;
            $startTotal = microtime(true);

            for ($i = 0; $i < $requestsCount; $i++) {
                $config = $payloadGenerator();
                $reqStart = microtime(true);

                if ($target === 'http') {
                    $response = Http::post($baseUrl . $config['url'], $config['data']);
                    $isSuccess = $response->successful();
                } else {
                    $request = \Illuminate\Http\Request::create(
                        $config['url'],
                        $config['method'],
                        $config['data']
                    );
                    $request->headers->set('Accept', 'application/json');
                    $response = app()->handle($request);
                    $isSuccess = $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
                }

                $durationMs = (microtime(true) - $reqStart) * 1000;
                $latencies[] = $durationMs;

                if ($isSuccess) {
                    $successes++;
                }
            }

            $totalDuration = microtime(true) - $startTotal;
            sort($latencies);

            $count = count($latencies);
            $min = round($latencies[0], 2);
            $max = round(end($latencies), 2);
            $avg = round(array_sum($latencies) / $count, 2);
            $p50 = round($latencies[(int) ($count * 0.50)], 2);
            $p95 = round($latencies[(int) ($count * 0.95)], 2);
            $p99 = round($latencies[(int) ($count * 0.99)], 2);
            $rps = round($count / $totalDuration, 2);
            $successRate = round(($successes / $count) * 100, 1);

            $this->output->writeln("<info>DONE</info> ({$rps} req/sec)");

            $results[] = [
                'Scenario' => $name,
                'Requests' => $count,
                'Success %' => $successRate . '%',
                'RPS' => $rps,
                'Avg (ms)' => $avg,
                'p50 (ms)' => $p50,
                'p95 (ms)' => $p95,
                'p99 (ms)' => $p99,
                'Min (ms)' => $min,
                'Max (ms)' => $max,
            ];
        }

        $this->newLine();
        $this->table(
            ['Scenario', 'Requests', 'Success %', 'Throughput (RPS)', 'Avg (ms)', 'p50 (ms)', 'p95 (ms)', 'p99 (ms)', 'Min (ms)', 'Max (ms)'],
            $results
        );

        $memoryPeakMb = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $this->info("Peak Memory Usage: {$memoryPeakMb} MB");
        $this->info('Benchmark completed successfully.');

        return Command::SUCCESS;
    }
}
