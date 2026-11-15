<?php

namespace App\DTOs\Digiflazz;

readonly class TransactionPlnDTO
{
    public function __construct(
        public string $username,
        public string $buyerSkuCode,
        public string $customerNo,
        public string $refId,
        public string $sign,
        public ?string $commands = 'pay-pasca',
        public ?bool $testing = false,
        public ?string $callbackUrl = null,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            username: $validated['username'],
            buyerSkuCode: $validated['buyer_sku_code'] ?? 'pln',
            customerNo: $validated['customer_no'],
            refId: $validated['ref_id'],
            sign: $validated['sign'],
            commands: $validated['commands'] ?? 'pay-pasca',
            testing: isset($validated['testing']) ? (bool) $validated['testing'] : false,
            callbackUrl: $validated['callback_url'] ?? null,
        );
    }
}
