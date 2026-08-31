<?php

namespace App\Domain\Payment\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function isFinal(): bool
    {
        return in_array($this, [
            self::Succeeded,
            self::Cancelled,
            self::Refunded,
        ], true);
    }
}
