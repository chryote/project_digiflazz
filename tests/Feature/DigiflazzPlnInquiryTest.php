<?php

namespace Tests\Feature;

use App\Constants\DigiflazzConstant;
use App\Models\User;
use Database\Seeders\PlnCustomerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DigiflazzPlnInquiryTest extends TestCase
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

    public function test_inquiry_pln_prepaid_id_sukses(): void
    {
        $customerNo = '1234554321';
        $sign = md5($this->user->username . $this->user->api_key . $customerNo);

        $response = $this->postJson('/api/v1/inquiry-pln', [
            'username' => $this->user->username,
            'customer_no' => $customerNo,
            'sign' => $sign,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Sukses')
            ->assertJsonPath('data.rc', '00')
            ->assertJsonPath('data.customer_no', '1234554321')
            ->assertJsonPath('data.name', 'DAVID')
            ->assertJsonPath('data.meter_no', '1234554321')
            ->assertJsonPath('data.subscriber_id', '523300817840')
            ->assertJsonPath('data.segment_power', 'R1 /000001300');
    }

    public function test_inquiry_pln_sukses_1_tagihan(): void
    {
        $customerNo = '530000000001';
        $sign = md5($this->user->username . $this->user->api_key . $customerNo);

        $response = $this->postJson('/api/v1/inquiry-pln', [
            'username' => $this->user->username,
            'customer_no' => $customerNo,
            'sign' => $sign,
            'ref_id' => 'some1d',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Sukses')
            ->assertJsonPath('data.rc', '00')
            ->assertJsonPath('data.customer_no', '530000000001')
            ->assertJsonPath('data.customer_name', 'Nama Pelanggan Pertama')
            ->assertJsonPath('data.admin', 2500)
            ->assertJsonPath('data.price', 10000)
            ->assertJsonPath('data.selling_price', 11000)
            ->assertJsonPath('data.desc.tarif', 'R1')
            ->assertJsonPath('data.desc.daya', 1300)
            ->assertJsonPath('data.desc.lembar_tagihan', 1)
            ->assertJsonPath('data.desc.detail.0.periode', '201901')
            ->assertJsonPath('data.desc.detail.0.nilai_tagihan', '8000')
            ->assertJsonPath('data.desc.detail.0.admin', '2500')
            ->assertJsonPath('data.desc.detail.0.denda', '500');
    }

    public function test_inquiry_pln_sukses_2_tagihan(): void
    {
        $customerNo = '530000000002';
        $sign = md5($this->user->username . $this->user->api_key . $customerNo);

        $response = $this->postJson('/api/v1/inquiry-pln', [
            'username' => $this->user->username,
            'customer_no' => $customerNo,
            'sign' => $sign,
            'ref_id' => 'some1d',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Sukses')
            ->assertJsonPath('data.rc', '00')
            ->assertJsonPath('data.customer_no', '530000000002')
            ->assertJsonPath('data.customer_name', 'Nama Pelanggan Kedua')
            ->assertJsonPath('data.admin', 5000)
            ->assertJsonPath('data.price', 23000)
            ->assertJsonPath('data.selling_price', 25000)
            ->assertJsonPath('data.desc.lembar_tagihan', 2)
            ->assertJsonCount(2, 'data.desc.detail');
    }

    public function test_inquiry_pln_gagal(): void
    {
        $customerNo = '530000000003';
        $sign = md5($this->user->username . $this->user->api_key . $customerNo);

        $response = $this->postJson('/api/v1/inquiry-pln', [
            'username' => $this->user->username,
            'customer_no' => $customerNo,
            'sign' => $sign,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Gagal')
            ->assertJsonPath('data.rc', '40')
            ->assertJsonPath('data.message', 'Inquiry Gagal');
    }

    public function test_inquiry_fails_with_invalid_signature(): void
    {
        $response = $this->postJson('/api/v1/inquiry-pln', [
            'username' => $this->user->username,
            'customer_no' => '530000000001',
            'sign' => 'invalid_signature_hash',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'Gagal')
            ->assertJsonPath('data.rc', '42')
            ->assertJsonPath('data.message', 'Signature tidak valid');
    }
}
