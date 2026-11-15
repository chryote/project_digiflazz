<?php

namespace App\Services\Digiflazz;

use App\DTOs\Digiflazz\InquiryPlnDTO;
use App\Enums\CustomerBehavior;
use App\Enums\DigiflazzResponseCode;
use App\Enums\DigiflazzStatus;
use App\Models\PlnCustomer;
use App\Models\User;

class PlnInquiryService
{
    public function __construct(
        protected DigiflazzSignatureService $signatureService
    ) {}

    /**
     * Process PLN Inquiry request according to Digiflazz specification.
     */
    public function inquire(InquiryPlnDTO $dto): array
    {
        $user = User::where('username', $dto->username)->first();

        if (! $user) {
            return [
                'data' => [
                    'message' => 'Username tidak ditemukan',
                    'status' => DigiflazzStatus::GAGAL->value,
                    'rc' => DigiflazzResponseCode::INVALID_SIGNATURE->value,
                    'customer_no' => $dto->customerNo,
                ],
            ];
        }

        // Validate signature: md5(username + apiKey + customer_no) or md5(username + apiKey + ref_id)
        $isValidSign = $this->signatureService->verify(
            username: $user->username,
            apiKey: $user->api_key,
            providedSign: $dto->sign,
            possibleSuffixes: array_filter([$dto->customerNo, $dto->refId])
        );

        if (! $isValidSign) {
            return [
                'data' => [
                    'message' => 'Signature tidak valid',
                    'status' => DigiflazzStatus::GAGAL->value,
                    'rc' => DigiflazzResponseCode::INVALID_SIGNATURE->value,
                    'customer_no' => $dto->customerNo,
                ],
            ];
        }

        $customer = PlnCustomer::where('customer_no', $dto->customerNo)->first();

        // Customer not found or inquiry failed test case (e.g. 530000000003)
        if (! $customer || $customer->behavior === CustomerBehavior::INQUIRY_FAILED->value) {
            return [
                'data' => [
                    'message' => 'Inquiry Gagal',
                    'status' => DigiflazzStatus::GAGAL->value,
                    'rc' => DigiflazzResponseCode::CUSTOMER_NOT_FOUND->value,
                    'customer_no' => $dto->customerNo,
                ],
            ];
        }

        // Section 1: ID / Prepaid PLN Inquiry format
        if ($customer->behavior === CustomerBehavior::PREPAID_INQUIRY->value) {
            return [
                'data' => [
                    'message' => 'Transaksi Sukses',
                    'status' => DigiflazzStatus::SUKSES->value,
                    'rc' => DigiflazzResponseCode::SUCCESS->value,
                    'customer_no' => $customer->customer_no,
                    'meter_no' => $customer->meter_no,
                    'subscriber_id' => $customer->subscriber_id,
                    'name' => $customer->customer_name,
                    'segment_power' => $customer->segment_power,
                ],
            ];
        }

        // Section 2: Postpaid Inquiry format
        return [
            'data' => [
                'ref_id' => $dto->refId ?? 'some1d',
                'customer_no' => $customer->customer_no,
                'customer_name' => $customer->customer_name,
                'buyer_sku_code' => $customer->buyer_sku_code ?? 'pln',
                'admin' => (int) $customer->admin,
                'message' => 'Transaksi Sukses',
                'status' => DigiflazzStatus::SUKSES->value,
                'rc' => DigiflazzResponseCode::SUCCESS->value,
                'buyer_last_saldo' => (int) $user->balance,
                'price' => (int) $customer->price,
                'selling_price' => (int) $customer->selling_price,
                'desc' => $customer->desc,
            ],
        ];
    }
}
