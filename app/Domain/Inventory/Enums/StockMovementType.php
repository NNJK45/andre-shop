<?php

namespace App\Domain\Inventory\Enums;

enum StockMovementType: string
{
    case Initial = 'initial';
    case Receipt = 'receipt';
    case Adjustment = 'adjustment';
    case Reservation = 'reservation';
    case Release = 'release';
    case Sale = 'sale';
    case Return = 'return';
}
