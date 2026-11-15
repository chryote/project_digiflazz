<?php

namespace Tests\Feature;

use App\Constants\DigiflazzConstant;
use App\Models\User;
use Database\Seeders\PlnCustomerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiTransactionSpecTest extends TestCase
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

    public function test_transaction_conforms_to_api_transaction_spec_structure(): void
    {
        $refId = 'TRX-' . uniqid();
        $customerNo = '530000000001';
        $sign = md5($this->user->username . $this->user->api_key . $refId);

        $payload = [
            'commands' => 'pay-pasca',
            'username' => $this->user->username,
            'buyer_sku_code' => 'pln',
            'customer_no' => $customerNo,
            'ref_id' => $refId,
            'sign' => $sign,
            'testing' => true,
        ];

        $response = $this->postJson('/api/v1/transaction', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'ref_id',
                    'customer_no',
                    'customer_name',
                    'buyer_sku_code',
                    'admin',
                    'message',
                    'status',
                    'rc',
                    'periode',
                    'sn',
                    'buyer_last_saldo',
                    'price',
                    'selling_price',
                    'desc' => [
                        'tarif',
                        'daya',
                        'lembar_tagihan',
                        'detail' => [
                            '*' => [
                                'periode',
                                'nilai_tagihan',
                                'admin',
                                'denda',
                            ],
                        ],
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertSame($refId, $data['ref_id']);
        $this->assertSame('Sukses', $data['status']);
        $this->assertSame('00', $data['rc']);
        $this->assertIsInt($data['admin']);
        $this->assertIsInt($data['price']);
        $this->assertIsInt($data['selling_price']);
        $this->assertIsFloat((float) $data['buyer_last_saldo']);
        $this->assertEquals(90000.0, (float) $data['buyer_last_saldo']);
        $this->assertSame('S1234554321N', $data['sn']);
    }

    public function test_cek_status_with_same_ref_id_returns_previous_transaction(): void
    {
        $refId = 'TRX-STATUS-CHECK';
        $customerNo = '530000000001';
        $sign = md5($this->user->username . $this->user->api_key . $refId);

        // 1. Initial transaction execution
        $first = $this->postJson('/api/v1/transaction', [
            'commands' => 'pay-pasca',
            'username' => $this->user->username,
            'buyer_sku_code' => 'pln',
            'customer_no' => $customerNo,
            'ref_id' => $refId,
            'sign' => $sign,
        ]);
        $first->assertStatus(200)->assertJsonPath('data.status', 'Sukses');

        $userBalance = $this->user->fresh()->balance;

        // 2. Cek status with same ref_id
        $check = $this->postJson('/api/v1/transaction', [
            'commands' => 'pay-pasca',
            'username' => $this->user->username,
            'buyer_sku_code' => 'pln',
            'customer_no' => $customerNo,
            'ref_id' => $refId,
            'sign' => $sign,
        ]);

        $check->assertStatus(200)
            ->assertJsonPath('data.ref_id', $refId)
            ->assertJsonPath('data.status', 'Sukses')
            ->assertJsonPath('data.rc', '00');

        // Balance remains unchanged (no double debit)
        $this->assertEquals($userBalance, $this->user->fresh()->balance);
    }

    public function test_transaction_pending_and_webhook_notification(): void
    {
        Http::fake([
            'https://webhook.site/*' => Http::response(['status' => 'received'], 200),
        ]);

        $refId = 'TRX-PENDING-001';
        $customerNo = '630000000001';
        $sign = md5($this->user->username . $this->user->api_key . $refId);

        $response = $this->postJson('/api/v1/transaction', [
            'commands' => 'pay-pasca',
            'username' => $this->user->username,
            'buyer_sku_code' => 'pln',
            'customer_no' => $customerNo,
            'ref_id' => $refId,
            'sign' => $sign,
            'callback_url' => 'https://webhook.site/transaction-webhook',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Pending')
            ->assertJsonPath('data.rc', '03')
            ->assertJsonPath('data.message', 'Transaksi Pending');

        Http::assertSent(function ($request) use ($refId) {
            return $request->url() === 'https://webhook.site/transaction-webhook'
                && $request['data']['ref_id'] === $refId
                && $request['data']['status'] === 'Sukses'
                && $request['data']['rc'] === '00';
        });
    }

    public function test_transaction_validation_errors(): void
    {
        $response = $this->postJson('/api/v1/transaction', [
            'commands' => 'pay-pasca',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Gagal')
            ->assertJsonPath('data.rc', '02');
    }
}
