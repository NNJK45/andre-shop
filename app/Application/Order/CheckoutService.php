<?php

namespace App\Application\Order;

use App\Application\Inventory\InventoryService;
use App\Domain\Cart\Models\Cart;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function checkout(User $user, array $attributes): Order
    {
        return DB::transaction(function () use ($user, $attributes): Order {
            $cart = Cart::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->with('items.purchasable')
                ->first();

            if (! $cart || $cart->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => ['The cart is empty.'],
                ]);
            }

            $subtotal = $cart->items->sum(
                fn ($item): float => (float) $item->unit_price * $item->quantity,
            );

            $order = Order::query()->create([
                'user_id' => $user->getKey(),
                'number' => $this->orderNumber(),
                'status' => OrderStatus::PendingPayment,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'currency' => 'XAF',
                'shipping_address' => $attributes['shipping_address'],
                'billing_address' => $attributes['billing_address'] ?? $attributes['shipping_address'],
                'placed_at' => now(),
            ]);

            foreach ($cart->items as $cartItem) {
                $purchasable = $cartItem->purchasable;
                $inventoryItem = InventoryItem::query()
                    ->whereMorphedTo('stockable', $purchasable)
                    ->first();

                if (! $inventoryItem) {
                    throw ValidationException::withMessages([
                        'stock' => ["Inventory is unavailable for SKU {$purchasable->sku}."],
                    ]);
                }

                $this->inventory->sellReserved(
                    $inventoryItem,
                    $cartItem->quantity,
                    $user,
                    [
                        'reason' => 'Order checkout',
                        'reference' => $order->number,
                    ],
                );

                $isVariant = $purchasable instanceof ProductVariant;
                $order->items()->create([
                    'purchasable_type' => $purchasable->getMorphClass(),
                    'purchasable_id' => $purchasable->getKey(),
                    'name' => $isVariant
                        ? "{$purchasable->product->name} - {$purchasable->name}"
                        : $purchasable->name,
                    'sku' => $purchasable->sku,
                    'attributes' => $isVariant ? $purchasable->attributes : null,
                    'quantity' => $cartItem->quantity,
                    'unit_price' => $cartItem->unit_price,
                    'total' => (float) $cartItem->unit_price * $cartItem->quantity,
                ]);
            }

            $order->statusHistory()->create([
                'changed_by_user_id' => $user->getKey(),
                'from_status' => null,
                'to_status' => OrderStatus::PendingPayment,
                'note' => 'Order placed.',
            ]);

            $cart->items()->delete();
            $cart->update(['expires_at' => null]);

            return $order->load(['items', 'statusHistory']);
        });
    }

    private function orderNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (Order::query()->where('number', $number)->exists());

        return $number;
    }
}
