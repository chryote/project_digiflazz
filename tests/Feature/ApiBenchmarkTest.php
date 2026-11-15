<?php

namespace Tests\Feature;

use App\Constants\DigiflazzConstant;
use App\Models\User;
use Database\Seeders\PlnCustomerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlnCustomerSeeder::class);

        $this->user = User::create([
            'name' => 'Benchmark Merchant',
            'email' => 'merchant@example.com',
            'username' => DigiflazzConstant::username(),
            'password' => Hash::make('password123'),
            'api_key' => DigiflazzConstant::developmentKey(),
            'balance' => 5000000,
        ]);
    }

    public function test_inquiry_pln_latency_and_throughput_benchmark(): void
    {
        $iterations = 50;
        $customerNo = '530000000001';
        $sign = md5($this->user->username . $this->user->api_key . $customerNo);

        $latencies = [];
        $startTime = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $reqStart = microtime(true);
            $response = $this->postJson('/api/v1/inquiry-pln', [
                'username' => $this->user->username,
                'customer_no' => $customerNo,
                'sign' => $sign,
                'ref_id' => 'BENCH-' . $i,
            ]);
            $latencies[] = (microtime(true) - $reqStart) * 1000;
            $response->assertStatus(200);
        }

        $totalDuration = microtime(true) - $startTime;
        $avgLatency = array_sum($latencies) / count($latencies);
        $rps = $iterations / $totalDuration;

        // Sub-50ms average latency requirement in local environment
        $this->assertLessThan(50.0, $avgLatency, "Average latency ({$avgLatency}ms) exceeded 50ms threshold");
        $this->assertGreaterThan(20.0, $rps, "Throughput ({$rps} RPS) below expected benchmark");
    }

    public function test_payment_pln_latency_and_throughput_benchmark(): void
    {
        $iterations = 30;
        $customerNo = '530000000001';

        $latencies = [];
        $startTime = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $refId = 'BENCH-PAY-' . $i;
            $sign = md5($this->user->username . $this->user->api_key . $refId);

            $reqStart = microtime(true);
            $response = $this->postJson('/api/v1/transaction', [
                'username' => $this->user->username,
                'buyer_sku_code' => 'pln',
                'customer_no' => $customerNo,
                'ref_id' => $refId,
                'sign' => $sign,
            ]);
            $latencies[] = (microtime(true) - $reqStart) * 1000;
            $response->assertStatus(200);
        }

        $totalDuration = microtime(true) - $startTime;
        $avgLatency = array_sum($latencies) / count($latencies);

        $this->assertLessThan(60.0, $avgLatency, "Average payment latency ({$avgLatency}ms) exceeded 60ms threshold");
    }
}
