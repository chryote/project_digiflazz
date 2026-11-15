<?php

namespace App\Services\Digiflazz;

use App\Constants\DigiflazzConstant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DigiflazzApiClient
{
    protected string $baseUrl;
    protected string $username;
    protected string $apiKey;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('digiflazz.base_url', 'https://api.digiflazz.com/v1'), '/');
        $this->username = DigiflazzConstant::username();
        $this->apiKey = DigiflazzConstant::developmentKey();
        $this->timeout = (int) config('digiflazz.timeout', 30);
    }

    /**
     * Call Live Digiflazz Prepaid PLN Inquiry (https://api.digiflazz.com/v1/inquiry-pln).
     */
    public function inquirePrepaid(string $customerNo, bool $testing = true): array
    {
        $sign = md5($this->username . $this->apiKey . $customerNo);
        $endpoint = "{$this->baseUrl}/inquiry-pln";

        $payload = [
            'username' => $this->username,
            'customer_no' => $customerNo,
            'sign' => $sign,
            'testing' => $testing,
        ];

        return $this->sendPostRequest($endpoint, $payload);
    }

    /**
     * Call Live Digiflazz Postpaid PLN Inquiry (https://api.digiflazz.com/v1/transaction).
     */
    public function inquirePostpaid(
        string $customerNo,
        string $refId,
        string $sku = 'pln',
        bool $testing = true
    ): array {
        $sign = md5($this->username . $this->apiKey . $refId);
        $endpoint = "{$this->baseUrl}/transaction";

        $payload = [
            'commands' => 'inq-pasca',
            'username' => $this->username,
            'buyer_sku_code' => $sku,
            'customer_no' => $customerNo,
            'ref_id' => $refId,
            'sign' => $sign,
            'testing' => $testing,
        ];

        return $this->sendPostRequest($endpoint, $payload);
    }

    /**
     * Call Live Digiflazz Postpaid PLN Payment (https://api.digiflazz.com/v1/transaction).
     */
    public function payPostpaid(
        string $customerNo,
        string $refId,
        string $sku = 'pln',
        ?string $callbackUrl = null,
        bool $testing = true
    ): array {
        $sign = md5($this->username . $this->apiKey . $refId);
        $endpoint = "{$this->baseUrl}/transaction";

        $payload = [
            'commands' => 'pay-pasca',
            'username' => $this->username,
            'buyer_sku_code' => $sku,
            'customer_no' => $customerNo,
            'ref_id' => $refId,
            'sign' => $sign,
            'testing' => $testing,
        ];

        if ($callbackUrl) {
            $payload['cb_url'] = $callbackUrl;
        }

        return $this->sendPostRequest($endpoint, $payload);
    }

    /**
     * Call Live Digiflazz Status Check (https://api.digiflazz.com/v1/transaction).
     */
    public function checkStatus(
        string $customerNo,
        string $refId,
        string $sku = 'pln',
        bool $testing = true
    ): array {
        $sign = md5($this->username . $this->apiKey . $refId);
        $endpoint = "{$this->baseUrl}/transaction";

        $payload = [
            'commands' => 'status-pasca',
            'username' => $this->username,
            'buyer_sku_code' => $sku,
            'customer_no' => $customerNo,
            'ref_id' => $refId,
            'sign' => $sign,
            'testing' => $testing,
        ];

        return $this->sendPostRequest($endpoint, $payload);
    }

    /**
     * Send HTTP POST request to Digiflazz API.
     */
    protected function sendPostRequest(string $endpoint, array $payload): array
    {
        try {
            Log::info("Dispatching HTTP request to live Digiflazz endpoint: {$endpoint}", [
                'commands' => $payload['commands'] ?? null,
                'customer_no' => $payload['customer_no'] ?? null,
                'ref_id' => $payload['ref_id'] ?? null,
            ]);

            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload);

            $data = $response->json();

            if (is_array($data) && isset($data['data'])) {
                return $data;
            }

            return [
                'data' => [
                    'message' => $response->body() ?: 'Respon kosong dari Digiflazz',
                    'status' => 'Gagal',
                    'rc' => (string) $response->status(),
                ],
            ];
        } catch (\Throwable $e) {
            Log::error("Digiflazz API connection error at {$endpoint}: " . $e->getMessage());

            return [
                'data' => [
                    'message' => 'Koneksi ke server Digiflazz gagal: ' . $e->getMessage(),
                    'status' => 'Gagal',
                    'rc' => '99',
                ],
            ];
        }
    }
}
