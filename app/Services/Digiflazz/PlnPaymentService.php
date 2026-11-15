<?php

namespace App\Services\Digiflazz;

use App\DTOs\Digiflazz\TransactionPlnDTO;
use App\Enums\CustomerBehavior;
use App\Enums\DigiflazzResponseCode;
use App\Enums\DigiflazzStatus;
use App\Models\PlnCustomer;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class PlnPaymentService
{
    public function __construct(
        protected DigiflazzSignatureService $signatureService,
        protected WebhookDispatchService $webhookService
    ) {}

    /**
     * Process PLN Payment transaction with ACID compliance, row locking, and race-condition prevention.
     */
    public function pay(TransactionPlnDTO $dto): array
    {
        // 1. Initial lookup
        $user = User::where('username', $dto->username)->first();

        if (! $user) {
            return [
                'data' => [
                    'ref_id' => $dto->refId,
                    'customer_no' => $dto->customerNo,
                    'buyer_sku_code' => $dto->buyerSkuCode,
                    'message' => 'Username tidak ditemukan',
                    'status' => DigiflazzStatus::GAGAL->value,
                    'rc' => DigiflazzResponseCode::INVALID_SIGNATURE->value,
                ],
            ];
        }

        // 2. Validate signature against ref_id or customer_no
        $isValidSign = $this->signatureService->verify(
            username: $user->username,
            apiKey: $user->api_key,
            providedSign: $dto->sign,
            possibleSuffixes: array_filter([$dto->refId, $dto->customerNo])
        );

        if (! $isValidSign) {
            return [
                'data' => [
                    'ref_id' => $dto->refId,
                    'customer_no' => $dto->customerNo,
                    'buyer_sku_code' => $dto->buyerSkuCode,
                    'message' => 'Signature tidak valid',
                    'status' => DigiflazzStatus::GAGAL->value,
                    'rc' => DigiflazzResponseCode::INVALID_SIGNATURE->value,
                ],
            ];
        }

        // 3. Fast-path check: Check if transaction already exists (Cek Status / Idempotency)
        $existing = Transaction::where('user_id', $user->id)
            ->where('ref_id', $dto->refId)
            ->first();

        if ($existing) {
            return $this->formatTransactionResponse($existing);
        }

        // 4. Validate customer
        $customer = PlnCustomer::where('customer_no', $dto->customerNo)->first();

        if (! $customer) {
            return [
                'data' => [
                    'ref_id' => $dto->refId,
                    'customer_no' => $dto->customerNo,
                    'buyer_sku_code' => $dto->buyerSkuCode,
                    'message' => 'Nomor Pelanggan Tidak Ditemukan',
                    'status' => DigiflazzStatus::GAGAL->value,
                    'rc' => DigiflazzResponseCode::CUSTOMER_NOT_FOUND->value,
                ],
            ];
        }

        // 5. ACID Transaction with Pessimistic Row Locking to eliminate Race Conditions & Double-Spending
        try {
            return DB::transaction(function () use ($user, $customer, $dto) {
                // 5.1 Lock and re-check existing transaction under row lock
                $lockedExisting = Transaction::where('user_id', $user->id)
                    ->where('ref_id', $dto->refId)
                    ->lockForUpdate()
                    ->first();

                if ($lockedExisting) {
                    return $this->formatTransactionResponse($lockedExisting);
                }

                // 5.2 Acquire Exclusive Row Lock (SELECT ... FOR UPDATE) on the User record
                // This serializes concurrent balance checks for the same user
                $lockedUser = User::where('id', $user->id)
                    ->lockForUpdate()
                    ->first();

                // 5.3 Test Case: Payment Failed (e.g. 530000000006)
                if ($customer->behavior === CustomerBehavior::PAYMENT_FAILED->value) {
                    $transaction = Transaction::create([
                        'user_id' => $lockedUser->id,
                        'ref_id' => $dto->refId,
                        'buyer_sku_code' => $dto->buyerSkuCode,
                        'customer_no' => $customer->customer_no,
                        'customer_name' => $customer->customer_name,
                        'admin' => $customer->admin,
                        'price' => $customer->price,
                        'selling_price' => $customer->selling_price,
                        'buyer_last_saldo' => (int) $lockedUser->balance,
                        'sn' => null,
                        'status' => DigiflazzStatus::GAGAL->value,
                        'rc' => DigiflazzResponseCode::PAYMENT_FAILED->value,
                        'message' => 'Pembayaran Gagal',
                        'desc' => $customer->desc,
                        'callback_url' => $dto->callbackUrl,
                    ]);

                    return $this->formatTransactionResponse($transaction);
                }

                // 5.4 Test Case: Pending then callback (630000000001, 630000000002, 630000000006)
                if (in_array($customer->behavior, [
                    CustomerBehavior::PENDING_CALLBACK_SUCCESS_1->value,
                    CustomerBehavior::PENDING_CALLBACK_SUCCESS_2->value,
                    CustomerBehavior::PENDING_CALLBACK_FAILED->value,
                ], true)) {
                    $transaction = Transaction::create([
                        'user_id' => $lockedUser->id,
                        'ref_id' => $dto->refId,
                        'buyer_sku_code' => $dto->buyerSkuCode,
                        'customer_no' => $customer->customer_no,
                        'customer_name' => $customer->customer_name,
                        'admin' => $customer->admin,
                        'price' => $customer->price,
                        'selling_price' => $customer->selling_price,
                        'buyer_last_saldo' => (int) $lockedUser->balance,
                        'sn' => null,
                        'status' => DigiflazzStatus::PENDING->value,
                        'rc' => DigiflazzResponseCode::PENDING->value,
                        'message' => 'Transaksi Pending',
                        'desc' => $customer->desc,
                        'callback_url' => $dto->callbackUrl,
                        'callback_status' => 'pending',
                    ]);

                    // Prepare callback payload
                    if ($customer->behavior === CustomerBehavior::PENDING_CALLBACK_FAILED->value) {
                        $callbackPayload = [
                            'data' => [
                                'ref_id' => $transaction->ref_id,
                                'customer_no' => $transaction->customer_no,
                                'customer_name' => $transaction->customer_name,
                                'buyer_sku_code' => $transaction->buyer_sku_code,
                                'admin' => (int) $transaction->admin,
                                'message' => 'Pembayaran Gagal',
                                'status' => DigiflazzStatus::GAGAL->value,
                                'rc' => DigiflazzResponseCode::PAYMENT_FAILED->value,
                                'periode' => $customer->desc['detail'][0]['periode'] ?? null,
                                'sn' => null,
                                'buyer_last_saldo' => (float) $lockedUser->balance,
                                'price' => (int) $transaction->price,
                                'selling_price' => (int) $transaction->selling_price,
                                'desc' => $transaction->desc,
                            ],
                        ];
                    } else {
                        // Atomically decrement balance under lock
                        $lockedUser->decrement('balance', $customer->price);
                        $newBalance = (int) $lockedUser->fresh()->balance;
                        $sn = 'S1234554321N';

                        $callbackPayload = [
                            'data' => [
                                'ref_id' => $transaction->ref_id,
                                'customer_no' => $transaction->customer_no,
                                'customer_name' => $transaction->customer_name,
                                'buyer_sku_code' => $transaction->buyer_sku_code,
                                'admin' => (int) $transaction->admin,
                                'message' => 'Transaksi Sukses',
                                'status' => DigiflazzStatus::SUKSES->value,
                                'rc' => DigiflazzResponseCode::SUCCESS->value,
                                'periode' => $customer->desc['detail'][0]['periode'] ?? null,
                                'sn' => $sn,
                                'buyer_last_saldo' => (float) $newBalance,
                                'price' => (int) $transaction->price,
                                'selling_price' => (int) $transaction->selling_price,
                                'desc' => $transaction->desc,
                            ],
                        ];
                    }

                    // Dispatch webhook notification
                    $this->webhookService->dispatch($transaction, $callbackPayload, $dto->callbackUrl);

                    return $this->formatTransactionResponse($transaction);
                }

                // 5.5 Normal Payment: Check balance under exclusive lock
                if ($lockedUser->balance < $customer->price) {
                    return [
                        'data' => [
                            'ref_id' => $dto->refId,
                            'customer_no' => $dto->customerNo,
                            'buyer_sku_code' => $dto->buyerSkuCode,
                            'message' => 'Saldo Tidak Cukup',
                            'status' => DigiflazzStatus::GAGAL->value,
                            'rc' => DigiflazzResponseCode::INSUFFICIENT_BALANCE->value,
                        ],
                    ];
                }

                // Atomically decrement balance and record transaction
                $lockedUser->decrement('balance', $customer->price);
                $newBalance = (int) $lockedUser->fresh()->balance;
                $sn = 'S1234554321N';

                $transaction = Transaction::create([
                    'user_id' => $lockedUser->id,
                    'ref_id' => $dto->refId,
                    'buyer_sku_code' => $dto->buyerSkuCode,
                    'customer_no' => $customer->customer_no,
                    'customer_name' => $customer->customer_name,
                    'admin' => $customer->admin,
                    'price' => $customer->price,
                    'selling_price' => $customer->selling_price,
                    'buyer_last_saldo' => $newBalance,
                    'sn' => $sn,
                    'status' => DigiflazzStatus::SUKSES->value,
                    'rc' => DigiflazzResponseCode::SUCCESS->value,
                    'message' => 'Transaksi Sukses',
                    'desc' => $customer->desc,
                    'callback_url' => $dto->callbackUrl,
                ]);

                return $this->formatTransactionResponse($transaction);
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $e) {
            // Concurrent race on same ref_id resolved: return the committed transaction safely
            $resolved = Transaction::where('user_id', $user->id)
                ->where('ref_id', $dto->refId)
                ->first();

            if ($resolved) {
                return $this->formatTransactionResponse($resolved);
            }

            throw $e;
        }
    }

    /**
     * Format transaction model into standardized Digiflazz response.
     */
    private function formatTransactionResponse(Transaction $transaction): array
    {
        $periode = $transaction->desc['detail'][0]['periode'] ?? null;

        return [
            'data' => [
                'ref_id' => $transaction->ref_id,
                'customer_no' => $transaction->customer_no,
                'customer_name' => $transaction->customer_name,
                'buyer_sku_code' => $transaction->buyer_sku_code,
                'admin' => (int) $transaction->admin,
                'message' => $transaction->message,
                'status' => $transaction->status,
                'rc' => $transaction->rc,
                'periode' => $periode,
                'sn' => $transaction->sn,
                'buyer_last_saldo' => (float) $transaction->buyer_last_saldo,
                'price' => (int) $transaction->price,
                'selling_price' => (int) $transaction->selling_price,
                'desc' => $transaction->desc,
            ],
        ];
    }
}
