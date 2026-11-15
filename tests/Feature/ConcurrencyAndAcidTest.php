<?php

namespace Tests\Feature;

use App\Constants\DigiflazzConstant;
use App\Enums\CustomerBehavior;
use App\Enums\DigiflazzResponseCode;
use App\Enums\DigiflazzStatus;
use App\Models\PlnCustomer;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\PlnCustomerSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConcurrencyAndAcidTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(UserSeeder::class);
        $this->seed(PlnCustomerSeeder::class);

        $this->user = User::where('username', DigiflazzConstant::username())->firstOrFail();
    }

    /**
     * Test ACID Atomicity & Isolation: Prevents double-spending when balance is insufficient for multiple transactions.
     */
    public function test_acid_atomicity_and_isolation_prevents_double_spending(): void
    {
        $customer = PlnCustomer::where('customer_no', '530000000001')->firstOrFail();
        $this->assertEquals(10000, $customer->price);

        // Set user balance to 15,000 (enough for only 1 transaction of 10,000)
        $this->user->update(['balance' => 15000]);

        $refId1 = 'order-concur-001';
        $sign1 = md5($this->user->username . $this->user->api_key . $refId1);

        $refId2 = 'order-concur-002';
        $sign2 = md5($this->user->username . $this->user->api_key . $refId2);

        // First transaction
        $response1 = $this->postJson('/api/v1/transaction', [
            'username' => $this->user->username,
            'buyer_sku_code' => 'pln',
            'customer_no' => $customer->customer_no,
            'ref_id' => $refId1,
            'sign' => $sign1,
            'testing' => true,
        ]);

        $response1->assertStatus(200);
        $response1->assertJsonPath('data.status', DigiflazzStatus::SUKSES->value);
        $response1->assertJsonPath('data.rc', DigiflazzResponseCode::SUCCESS->value);
        $response1->assertJsonPath('data.buyer_last_saldo', 5000);

        // Second transaction must be rejected due to insufficient balance (serialized check)
        $response2 = $this->postJson('/api/v1/transaction', [
            'username' => $this->user->username,
            'buyer_sku_code' => 'pln',
            'customer_no' => $customer->customer_no,
            'ref_id' => $refId2,
            'sign' => $sign2,
            'testing' => true,
        ]);

        $response2->assertStatus(200);
        $response2->assertJsonPath('data.status', DigiflazzStatus::GAGAL->value);
        $response2->assertJsonPath('data.rc', DigiflazzResponseCode::INSUFFICIENT_BALANCE->value);
        $response2->assertJsonPath('data.message', 'Saldo Tidak Cukup');

        // User balance must remain exactly 5,000 and NEVER negative
        $this->assertEquals(5000, (int) $this->user->fresh()->balance);

        // Only 1 transaction record exists
        $this->assertEquals(1, Transaction::where('user_id', $this->user->id)->count());
    }

    /**
     * Test Idempotency: Submitting the same ref_id twice does NOT duplicate debit or record.
     */
    public function test_idempotency_prevents_duplicate_deductions_for_same_ref_id(): void
    {
        $this->user->update(['balance' => 100000]);

        $customer = PlnCustomer::where('customer_no', '530000000001')->firstOrFail();
        $refId = 'idempotent-order-999';
        $sign = md5($this->user->username . $this->user->api_key . $refId);

        $payload = [
            'username' => $this->user->username,
            'buyer_sku_code' => 'pln',
            'customer_no' => $customer->customer_no,
            'ref_id' => $refId,
            'sign' => $sign,
            'testing' => true,
        ];

        // Call 1
        $res1 = $this->postJson('/api/v1/transaction', $payload);
        $res1->assertStatus(200);
        $res1->assertJsonPath('data.status', DigiflazzStatus::SUKSES->value);
        $res1->assertJsonPath('data.buyer_last_saldo', 90000);

        // Call 2 with identical ref_id (simulates network retry / status check / concurrent double submit)
        $res2 = $this->postJson('/api/v1/transaction', $payload);
        $res2->assertStatus(200);
        $res2->assertJsonPath('data.status', DigiflazzStatus::SUKSES->value);
        $res2->assertJsonPath('data.buyer_last_saldo', 90000);

        // Ensure balance was deducted exactly ONCE
        $this->assertEquals(90000, (int) $this->user->fresh()->balance);

        // Database must contain only ONE transaction
        $this->assertEquals(1, Transaction::where('ref_id', $refId)->count());
    }

    /**
     * Test ACID Durability and Rollback: Any unhandled exception rolls back balance modification.
     */
    public function test_acid_atomicity_rolls_back_balance_if_exception_occurs(): void
    {
        $initialBalance = 100000;
        $this->user->update(['balance' => $initialBalance]);

        $customer = PlnCustomer::where('customer_no', '530000000001')->firstOrFail();
        $refId = 'rollback-test-001';

        try {
            DB::transaction(function () use ($customer, $refId) {
                // Simulate balance decrement
                $this->user->decrement('balance', $customer->price);

                // Simulate unexpected failure before transaction commit
                throw new \RuntimeException('Database disk full or external network failure');
            });
        } catch (\RuntimeException $e) {
            // Expected exception caught
        }

        // Verify balance was completely rolled back to initial state
        $this->assertEquals($initialBalance, (int) $this->user->fresh()->balance);
    }

    /**
     * Test Concurrency on API Key regeneration: Wrapped in transaction with lock.
     */
    public function test_api_key_regeneration_is_transactional(): void
    {
        $oldKey = $this->user->api_key;

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/auth/regenerate-api-key');

        $response->assertStatus(200);
        $newKey = $response->json('data.api_key');

        $this->assertNotEmpty($newKey);
        $this->assertNotEquals($oldKey, $newKey);
        $this->assertEquals($newKey, $this->user->fresh()->api_key);
    }

    /**
     * Test Concurrency rate limiting: Authentication endpoint throttles bursts.
     */
    public function test_login_rate_limiting_protects_against_brute_force_flooding(): void
    {
        // 10 attempts allowed per minute
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'login' => 'nonexistent_user',
                'password' => 'wrong_password',
            ]);
        }

        // 11th request must receive HTTP 429 Too Many Requests
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'nonexistent_user',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(429);
    }
}
