<?php

namespace Tests\Feature;

use App\Constants\DigiflazzConstant;
use App\Models\User;
use App\Services\Digiflazz\DigiflazzApiClient;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LiveDigiflazzApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DigiflazzApiClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        config(['digiflazz.mode' => 'live']);

        $this->seed(UserSeeder::class);
        $this->user = User::where('username', DigiflazzConstant::username())->firstOrFail();
        $this->client = app(DigiflazzApiClient::class);
    }

    /**
     * Test live outbound call for Postpaid Inquiry (https://api.digiflazz.com/v1/transaction).
     */
    public function test_live_digiflazz_inquiry_postpaid_returns_success(): void
    {
        $refId = 'LIVE-INQ-' . time();
        $result = $this->client->inquirePostpaid('530000000001', $refId, 'pln', true);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('Sukses', $result['data']['status']);
        $this->assertEquals('00', $result['data']['rc']);
        $this->assertEquals('530000000001', $result['data']['customer_no']);
    }

    /**
     * Test live outbound call for Payment Success (https://api.digiflazz.com/v1/transaction).
     */
    public function test_live_digiflazz_payment_postpaid_returns_success(): void
    {
        $refId = 'LIVE-PAY-' . time() . '-' . rand(100, 999);
        $result = $this->client->payPostpaid('530000000001', $refId, 'pln', null, true);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('Sukses', $result['data']['status']);
        $this->assertEquals('00', $result['data']['rc']);
        $this->assertNotEmpty($result['data']['sn']);
    }

    /**
     * Test live outbound call for Payment Failed testcase (530000000006).
     */
    public function test_live_digiflazz_payment_failed_testcase(): void
    {
        $refId = 'LIVE-FAIL-' . time();
        $result = $this->client->payPostpaid('530000000006', $refId, 'pln', null, true);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('Gagal', $result['data']['status']);
        $this->assertEquals('02', $result['data']['rc']);
    }

    /**
     * Test live outbound call for Status Check (https://api.digiflazz.com/v1/transaction).
     */
    public function test_live_digiflazz_status_check(): void
    {
        $refId = 'LIVE-CHK-' . time();
        // Execute initial payment
        $this->client->payPostpaid('530000000001', $refId, 'pln', null, true);

        // Check status
        $statusResult = $this->client->checkStatus('530000000001', $refId, 'pln', true);

        $this->assertIsArray($statusResult);
        $this->assertArrayHasKey('data', $statusResult);
        $this->assertEquals('Sukses', $statusResult['data']['status']);
        $this->assertEquals('00', $statusResult['data']['rc']);
        $this->assertEquals($refId, $statusResult['data']['ref_id']);
    }

    /**
     * Test controller forwarding in live mode.
     */
    public function test_controller_forwards_inquiry_to_live_api(): void
    {
        $refId = 'INQ-CTRL-' . time();
        $sign = md5($this->user->username . $this->user->api_key . '530000000001');

        $response = $this->postJson('/api/v1/inquiry-pln', [
            'commands' => 'inq-pasca',
            'username' => $this->user->username,
            'buyer_sku_code' => 'pln',
            'customer_no' => '530000000001',
            'ref_id' => $refId,
            'sign' => $sign,
            'testing' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'Sukses');
        $response->assertJsonPath('data.rc', '00');
    }
}
