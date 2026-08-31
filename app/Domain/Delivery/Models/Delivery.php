<?php

namespace App\Domain\Delivery\Models;

use App\Domain\Delivery\Enums\DeliveryStatus;
use App\Domain\Order\Models\Order;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'tracking_number',
        'status',
        'provider',
        'recipient_name',
        'recipient_phone',
        'recipient_address',
        'failure_reason',
        'notes',
        'assigned_at',
        'picked_up_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'recipient_address' => 'array',
            'assigned_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function getRouteKeyName(): string
    {
        return 'tracking_number';
    }
}
