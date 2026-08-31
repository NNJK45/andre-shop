<?php

namespace App\Application\Payment\DTO;

final readonly class PaymentInitialization
{
    public function __construct(
        public string $providerReference,
        public ?string $checkoutUrl = null,
        public array $metadata = [],
    ) {}
}
