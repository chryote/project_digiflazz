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
        protected DigiflazzSignatureService $signatureService,
        protected DigiflazzApiClient $apiClient
    ) {}

    /**
     * Process PLN Inquiry request by hitting live Digiflazz or local mock fallback.
     */
    public function inquire(InquiryPlnDTO $dto): array
    {
        // 1. Initial User Lookup
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

        // 2. Validate Signature: md5(username + apiKey + customer_no) or md5(username + apiKey + ref_id)
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

        // 3. Live Mode: Dispatch HTTP request to live Digiflazz API
        if (config('digiflazz.mode', 'live') === 'live') {
            $isPostpaid = ($dto->commands === 'inq-pasca')
                || ($dto->refId !== null)
                || str_starts_with($dto->customerNo, '53')
                || str_starts_with($dto->customerNo, '63');

            if ($isPostpaid) {
                // Postpaid inquiry goes to https://api.digiflazz.com/v1/transaction with commands=inq-pasca
                $refId = $dto->refId ?? ('INQ-' . time());
                $liveResult = $this->apiClient->inquirePostpaid(
                    customerNo: $dto->customerNo,
                    refId: $refId,
                    sku: $dto->buyerSkuCode ?? 'pln',
                    testing: $dto->testing ?? true
                );
            } else {
                // Prepaid PLN ID inquiry goes to https://api.digiflazz.com/v1/inquiry-pln
                $liveResult = $this->apiClient->inquirePrepaid(
                    customerNo: $dto->customerNo,
                    testing: $dto->testing ?? true
                );
            }

            // If live response contains buyer_last_saldo, synchronize with local user balance if applicable
            if (isset($liveResult['data']) && is_array($liveResult['data'])) {
                if (isset($liveResult['data']['buyer_last_saldo'])) {
                    $liveResult['data']['buyer_last_saldo'] = (int) $user->balance;
                }

                return $liveResult;
            }
        }

        // 4. Mock / Local Sandbox Fallback
        return $this->inquireLocalMock($dto, $user);
    }

    /**
     * Process inquiry using local seeded database for offline testing.
     */
    protected function inquireLocalMock(InquiryPlnDTO $dto, User $user): array
    {
        $customer = PlnCustomer::where('customer_no', $dto->customerNo)->first();

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
