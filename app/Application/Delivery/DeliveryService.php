<?php

namespace App\Application\Delivery;

use App\Application\Notification\NotificationService;
use App\Domain\Delivery\Enums\DeliveryStatus;
use App\Domain\Delivery\Models\Delivery;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function create(Order $order, array $attributes): Delivery
    {
        if (in_array($order->status, [OrderStatus::PendingPayment, OrderStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'order_id' => ['A delivery can only be created for a paid or processed order.'],
            ]);
        }

        if ($order->delivery()->exists()) {
            throw ValidationException::withMessages([
                'order_id' => ['This order already has a delivery.'],
            ]);
        }

        $address = $order->shipping_address ?? [];

        return $order->delivery()->create([
            'tracking_number' => $this->trackingNumber(),
            'status' => DeliveryStatus::Pending,
            'provider' => $attributes['provider'] ?? null,
            'recipient_name' => $attributes['recipient_name'] ?? ($address['full_name'] ?? $order->user?->name ?? 'Customer'),
            'recipient_phone' => $attributes['recipient_phone'] ?? ($address['phone'] ?? ''),
            'recipient_address' => $attributes['recipient_address'] ?? $address,
            'notes' => $attributes['notes'] ?? null,
        ]);
    }

    public function transition(Delivery $delivery, DeliveryStatus $status, User $user, ?string $failureReason = null): Delivery
    {
        return DB::transaction(function () use ($delivery, $status, $user, $failureReason): Delivery {
            $locked = Delivery::query()->lockForUpdate()->findOrFail($delivery->getKey());

            if (! $locked->status->canTransitionTo($status)) {
                throw ValidationException::withMessages([
                    'status' => ["Cannot transition a delivery from {$locked->status->value} to {$status->value}."],
                ]);
            }

            $now = now();
            $updates = [
                'status' => $status,
                'failure_reason' => $status === DeliveryStatus::Failed ? $failureReason : $locked->failure_reason,
                'assigned_at' => $status === DeliveryStatus::Assigned ? ($locked->assigned_at ?? $now) : $locked->assigned_at,
                'picked_up_at' => $status === DeliveryStatus::PickedUp ? ($locked->picked_up_at ?? $now) : $locked->picked_up_at,
                'delivered_at' => $status === DeliveryStatus::Delivered ? ($locked->delivered_at ?? $now) : $locked->delivered_at,
            ];
            $locked->update($updates);

            $order = $locked->order()->with('user')->lockForUpdate()->firstOrFail();

            if ($status === DeliveryStatus::Delivered && $order->status === OrderStatus::Shipped) {
                $order->update(['status' => OrderStatus::Delivered]);
                $order->statusHistory()->create([
                    'changed_by_user_id' => $user->getKey(),
                    'from_status' => OrderStatus::Shipped,
                    'to_status' => OrderStatus::Delivered,
                    'note' => 'Delivery completed.',
                ]);
            }

            $this->notifications->send($order->user, 'delivery.status_changed', [
                'tracking_number' => $locked->tracking_number,
                'order_number' => $order->number,
                'status' => $status->value,
            ]);

            return $locked->refresh()->load('order');
        });
    }

    private function trackingNumber(): string
    {
        do {
            $tracking = 'DLV-'.now()->format('Ymd').'-'.Str::upper(Str::random(10));
        } while (Delivery::query()->where('tracking_number', $tracking)->exists());

        return $tracking;
    }
}
