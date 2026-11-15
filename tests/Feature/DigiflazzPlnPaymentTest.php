<?php

namespace Tests\Feature;

use App\Constants\DigiflazzConstant;
use App\Models\User;
use Database\Seeders\PlnCustomerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DigiflazzPlnPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlnCustomerSeeder::class);

        $this->user = User::create([
            'name' => 'Merchant Test',
            'email' => 'merchant@example.com',
            'username' => DigiflazzConstant::username(),
            'password' => Hash::make('password123'),
            'api_key' => DigiflazzConstant::developmentKey(),
            'balance' => 100000,
        ]);
    }

    public function test_payment_sukses_1_tagihan(): void
    {
        $customerNo = '530000000001';
        $refId = 'ORDER-001';
        $sign = md5($this->user->username . $this->user->api_key . $refId);

        $response = $this->postJson('/api/v1/transaction', [
            'username' => $this->user->username,
            'buyer_sku_code' => 'pln',
            'customer_no' => $customerNo,
            'ref_id' => $refId,
            'sign' => $sign,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Sukses')
            ->assertJsonPath('data.rc', '00')
            ->assertJsonPath('data.customer_no', '530000000001')
            ->assertJsonPath('data.customer_name', 'Nama Pelanggan Pertama')
            ->assertJsonPath('data.sn', 'S1234554321N')
            ->assertJsonPath('data.admin', 2500)
            ->assertJsonPath('data.price', 10000)
            ->assertJsonPath('data.selling_price', 11000)
            ->assertJsonPath('data.buyer_last_saldo', 90000);

        // Ensure balance was deducted
        $this->assertEquals(90000, $this->user->fresh()->balance);
    }

    public function test_payment_sukses_2_tagihan(): void
    {
        $customerNo = '530000000002';
        $refId = 'ORDER-002';
        $sign = md5($this->user->username . $this->user->api_key . $refId);

        $response = $this->postJson('/api/v1/transaction', [
            'username' => $this->user->username,
            'buyer_sku_code' => 'pln',
            'customer_no' => $customerNo,
            'ref_id' => $refId,
            'sign' => $sign,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Sukses')
            ->assertJsonPath('data.rc', '00')
            ->assertJsonPath('data.customer_no', '530000000002')
            ->assertJsonPath('data.customer_name', 'Nama Pelanggan Kedua')
            ->assertJsonPath('data.admin', 5000)
            ->assertJsonPath('data.price', 23000)
            ->assertJsonPath('data.selling_price', 25000)
            ->assertJsonPath('data.buyer_last_saldo', 77000)
            ->assertJsonCount(2, 'data.desc.detail');

        // Balance 100000 - 23000 = 77000
        $this->assertEquals(77000, $this->user->fresh()->balance);
    }

    public function test_pembayaran_gagal_testcase(): void
    {
        $customerNo = '530000000006';
        $refId = 'ORDER-006';
        $sign = md5($this->user->username . $this->user->api_key . $refId);

        $response = $this->postJson('/api/v1/transaction', [
            'username' => $this->user->username,
            'buyer_sku_code' => 'pln',
            'customer_no' => $customerNo,
            'ref_id' => $refId,
            'sign' => $sign,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Gagal')
            ->assertJsonPath('data.rc', '41')
            ->assertJsonPath('data.message', 'Pembayaran Gagal');

        // Balance should NOT be deducted
        $this->assertEquals(100000, $this->user->fresh()->balance);
    }

    public function test_pending_lalu_callback_sukses_1_tagihan(): void
    {
        Http::fake([
            'https://webhook.site/*' => Http::response(['ok' => true], 200),
        ]);

        $customerNo = '630000000001';
        $refId = 'ORDER-631';
        $sign = md5($this->user->username . $this->user->api_key . $refId);

        $response = $this->postJson('/api/v1/transaction', [
            'username' => $this->user->username,
            'buyer_sku_code' => 'pln',
            'customer_no' => $customerNo,
            'ref_id' => $refId,
            'sign' => $sign,
            'callback_url' => 'https://webhook.site/test-callback',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Pending')
            ->assertJsonPath('data.rc', '03');

        // Check webhook was dispatched
        Http::assertSent(function ($request) {
            return $request->url() === 'https://webhook.site/test-callback'
                && $request['data']['status'] === 'Sukses'
                && $request['data']['rc'] === '00';
        });
    }

    public function test_pending_lalu_callback_sukses_2_tagihan(): void
    {
        Http::fake([
            'https://webhook.site/*' => Http::response(['ok' => true], 200),
        ]);

        $customerNo = '630000000002';
        $refId = 'ORDER-632';
        $sign = md5($this->user->username . $this->user->api_key . $refId);

        $response = $this->postJson('/api/v1/transaction', [
            'username' => $this->user->username,
            'buyer_sku_code' => 'pln',
            'customer_no' => $customerNo,
            'ref_id' => $refId,
            'sign' => $sign,
            'callback_url' => 'https://webhook.site/test-callback',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Pending')
            ->assertJsonPath('data.rc', '03');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://webhook.site/test-callback'
                && $request['data']['status'] === 'Sukses'
                && $request['data']['rc'] === '00'
                && count($request['data']['desc']['detail']) === 2;
        });
    }

    public function test_pending_lalu_callback_gagal(): void
    {
        Http::fake([
            'https://webhook.site/*' => Http::response(['ok' => true], 200),
        ]);

        $customerNo = '630000000006';
        $refId = 'ORDER-636';
        $sign = md5($this->user->username . $this->user->api_key . $refId);

        $response = $this->postJson('/api/v1/transaction', [
            'username' => $this->user->username,
            'buyer_sku_code' => 'pln',
            'customer_no' => $customerNo,
            'ref_id' => $refId,
            'sign' => $sign,
            'callback_url' => 'https://webhook.site/test-callback',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Pending')
            ->assertJsonPath('data.rc', '03');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://webhook.site/test-callback'
                && $request['data']['status'] === 'Gagal'
                && $request['data']['rc'] === '41';
        });
    }

    public function test_payment_idempotency_prevents_double_charging(): void
    {
        $customerNo = '530000000001';
        $refId = 'ORDER-IDEMPOTENT';
        $sign = md5($this->user->username . $this->user->api_key . $refId);

        // First call
        $first = $this->postJson('/api/v1/transaction', [
            'username' => $this->user->username,
            'buyer_sku_code' => 'pln',
            'customer_no' => $customerNo,
            'ref_id' => $refId,
            'sign' => $sign,
        ]);
        $first->assertStatus(200)->assertJsonPath('data.status', 'Sukses');

        $balanceAfterFirst = $this->user->fresh()->balance;

        // Second call with same ref_id
        $second = $this->postJson('/api/v1/transaction', [
            'username' => $this->user->username,
            'buyer_sku_code' => 'pln',
            'customer_no' => $customerNo,
            'ref_id' => $refId,
            'sign' => $sign,
        ]);
        $second->assertStatus(200)
            ->assertJsonPath('data.status', 'Sukses')
            ->assertJsonPath('data.ref_id', $refId);

        // Balance must remain unchanged
        $this->assertEquals($balanceAfterFirst, $this->user->fresh()->balance);
    }
}
