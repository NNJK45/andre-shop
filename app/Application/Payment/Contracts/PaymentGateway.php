<?php

namespace App\Application\Payment\Contracts;

use App\Application\Payment\DTO\PaymentInitialization;
use App\Application\Payment\DTO\PaymentStatusResult;
use App\Domain\Payment\Models\Payment;

interface PaymentGateway
{
    public function initialize(Payment $payment): PaymentInitialization;

    public function status(Payment $payment): PaymentStatusResult;
}
