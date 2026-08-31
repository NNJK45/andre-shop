<?php

namespace App\Infrastructure\Payment;

use App\Application\Payment\Contracts\PaymentGateway;
use App\Application\Payment\DTO\PaymentInitialization;
use App\Application\Payment\DTO\PaymentStatusResult;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;

class FakePaymentGateway implements PaymentGateway
{
    public function initialize(Payment $payment): PaymentInitialization
    {
        return new PaymentInitialization(
            providerReference: 'FAKE-'.$payment->reference,
            checkoutUrl: 'https://payments.example.test/'.$payment->reference,
            metadata: array_merge($payment->metadata ?? [], ['driver' => 'fake']),
        );
    }

    public function status(Payment $payment): PaymentStatusResult
    {
        return new PaymentStatusResult(
            providerReference: $payment->provider_reference ?? 'FAKE-'.$payment->reference,
            merchantReference: $payment->reference,
            status: PaymentStatus::Pending,
            amount: (string) $payment->amount,
            payload: ['status' => 'PENDING'],
        );
    }
}
