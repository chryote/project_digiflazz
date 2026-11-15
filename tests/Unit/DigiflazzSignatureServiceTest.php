<?php

namespace Tests\Unit;

use App\Services\Digiflazz\DigiflazzSignatureService;
use PHPUnit\Framework\TestCase;

class DigiflazzSignatureServiceTest extends TestCase
{
    private DigiflazzSignatureService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DigiflazzSignatureService();
    }

    public function test_it_generates_md5_signature(): void
    {
        $username = 'username';
        $apiKey = 'dev_api_key';
        $customerNo = '1234554321';

        $expected = md5($username . $apiKey . $customerNo);
        $signature = $this->service->generate($username, $apiKey, $customerNo);

        $this->assertEquals($expected, $signature);
    }

    public function test_it_verifies_signature_with_customer_no(): void
    {
        $username = 'username';
        $apiKey = 'dev_api_key';
        $customerNo = '530000000001';

        $validSign = md5($username . $apiKey . $customerNo);

        $this->assertTrue(
            $this->service->verify($username, $apiKey, $validSign, [$customerNo])
        );
    }

    public function test_it_verifies_signature_with_ref_id(): void
    {
        $username = 'username';
        $apiKey = 'dev_api_key';
        $refId = 'ORDER-12345';

        $validSign = md5($username . $apiKey . $refId);

        $this->assertTrue(
            $this->service->verify($username, $apiKey, $validSign, [$refId])
        );
    }

    public function test_it_rejects_invalid_signature(): void
    {
        $username = 'username';
        $apiKey = 'dev_api_key';
        $customerNo = '530000000001';

        $invalidSign = 'invalid_md5_hash_string';

        $this->assertFalse(
            $this->service->verify($username, $apiKey, $invalidSign, [$customerNo])
        );
    }
}
