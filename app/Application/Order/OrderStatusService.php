<?php

namespace App\Application\Order;

use App\Application\Inventory\InventoryService;
use App\Application\Notification\NotificationService;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStatusService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly NotificationService $notifications,
    ) {}

    public function transition(Order $order, OrderStatus $status, User $user, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $status, $user, $note): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $current = $locked->status;

            if (! $current->canTransitionTo($status)) {
                throw ValidationException::withMessages([
                    'status' => ["Cannot transition an order from {$current->value} to {$status->value}."],
                ]);
            }

            if ($status === OrderStatus::Cancelled) {
                $locked->load('items.purchasable');

                foreach ($locked->items as $item) {
                    if (! $item->purchasable) {
                        continue;
                    }

                    $inventoryItem = InventoryItem::query()
                        ->whereMorphedTo('stockable', $item->purchasable)
                        ->first();

                    if ($inventoryItem) {
                        $this->inventory->returnStock(
                            $inventoryItem,
                            $item->quantity,
                            $user,
                            [
                                'reason' => 'Order cancelled',
                                'reference' => $locked->number,
                            ],
                        );
                    }
                }
            }

            $locked->update(['status' => $status]);
            $locked->statusHistory()->create([
                'changed_by_user_id' => $user->getKey(),
                'from_status' => $current,
                'to_status' => $status,
                'note' => $note,
            ]);

            $locked->load('user');
            $this->notifications->send($locked->user, 'order.status_changed', [
                'order_number' => $locked->number,
                'from_status' => $current->value,
                'status' => $status->value,
                'note' => $note,
            ]);

            return $locked->refresh()->load(['user', 'items', 'statusHistory']);
        });
    }
}
