<?php

namespace App\Application\Payment\DTO;

use App\Domain\Payment\Enums\PaymentStatus;

final readonly class PaymentStatusResult
{
    public function __construct(
        public string $providerReference,
        public string $merchantReference,
        public PaymentStatus $status,
        public string $amount,
        public array $payload,
    ) {}
}
