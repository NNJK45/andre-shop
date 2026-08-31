<?php

namespace App\Domain\Delivery\Enums;

enum DeliveryStatus: string
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $status): bool
    {
        return match ($this) {
            self::Pending => in_array($status, [self::Assigned, self::Cancelled], true),
            self::Assigned => in_array($status, [self::PickedUp, self::Cancelled], true),
            self::PickedUp => in_array($status, [self::InTransit, self::Failed, self::Cancelled], true),
            self::InTransit => in_array($status, [self::Delivered, self::Failed, self::Cancelled], true),
            self::Failed => in_array($status, [self::InTransit, self::Cancelled], true),
            default => false,
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::Cancelled], true);
    }
}
