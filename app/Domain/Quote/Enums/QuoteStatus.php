<?php

namespace App\Domain\Quote\Enums;

enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Received = 'received';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $status): bool
    {
        return match ($this) {
            self::Draft => in_array($status, [self::Sent, self::Cancelled], true),
            self::Sent => in_array($status, [self::Received, self::Cancelled], true),
            self::Received => in_array($status, [self::Accepted, self::Rejected, self::Cancelled], true),
            default => false,
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Accepted, self::Rejected, self::Cancelled], true);
    }
}
