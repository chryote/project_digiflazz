<?php

namespace App\DTOs\Digiflazz;

readonly class InquiryPlnDTO
{
    public function __construct(
        public string $username,
        public string $customerNo,
        public string $sign,
        public ?string $refId = null,
        public ?string $buyerSkuCode = 'pln',
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            username: $validated['username'],
            customerNo: $validated['customer_no'],
            sign: $validated['sign'],
            refId: $validated['ref_id'] ?? null,
            buyerSkuCode: $validated['buyer_sku_code'] ?? 'pln',
        );
    }
}
