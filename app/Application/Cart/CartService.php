<?php

namespace App\Application\Cart;

use App\Application\Inventory\InventoryService;
use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\InventoryItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function get(User $user): Cart
    {
        return $this->cartFor($user)->load('items.purchasable');
    }

    public function add(User $user, array $attributes): Cart
    {
        return DB::transaction(function () use ($user, $attributes): Cart {
            $cart = $this->lockedCartFor($user);
            $purchasable = $this->resolvePurchasable($attributes);
            $inventoryItem = $this->inventoryFor($purchasable);

            $item = $cart->items()
                ->whereMorphedTo('purchasable', $purchasable)
                ->lockForUpdate()
                ->first();

            $quantity = (int) $attributes['quantity'];
            $this->inventory->reserve(
                $inventoryItem,
                $quantity,
                $user,
                $this->reservationMetadata($cart),
            );

            if ($item) {
                $item->increment('quantity', $quantity);
            } else {
                $item = new CartItem([
                    'quantity' => $quantity,
                    'unit_price' => $this->priceFor($purchasable),
                ]);
                $item->purchasable()->associate($purchasable);
                $cart->items()->save($item);
            }

            $this->refreshExpiry($cart);

            return $cart->refresh()->load('items.purchasable');
        });
    }

    public function update(User $user, int $itemId, int $quantity): Cart
    {
        return DB::transaction(function () use ($user, $itemId, $quantity): Cart {
            $cart = $this->lockedCartFor($user);
            $item = $this->ownedItem($cart, $itemId);
            $difference = $quantity - $item->quantity;
            $inventoryItem = $this->inventoryFor($item->purchasable);

            if ($difference > 0) {
                $this->inventory->reserve(
                    $inventoryItem,
                    $difference,
                    $user,
                    $this->reservationMetadata($cart),
                );
            } elseif ($difference < 0) {
                $this->inventory->release(
                    $inventoryItem,
                    abs($difference),
                    $user,
                    $this->reservationMetadata($cart),
                );
            }

            $item->update([
                'quantity' => $quantity,
                'unit_price' => $this->priceFor($item->purchasable),
            ]);
            $this->refreshExpiry($cart);

            return $cart->refresh()->load('items.purchasable');
        });
    }

    public function remove(User $user, int $itemId): Cart
    {
        return DB::transaction(function () use ($user, $itemId): Cart {
            $cart = $this->lockedCartFor($user);
            $item = $this->ownedItem($cart, $itemId);

            $this->inventory->release(
                $this->inventoryFor($item->purchasable),
                $item->quantity,
                $user,
                $this->reservationMetadata($cart),
            );
            $item->delete();
            $this->refreshExpiry($cart);

            return $cart->refresh()->load('items.purchasable');
        });
    }

    public function clear(User $user): Cart
    {
        return DB::transaction(function () use ($user): Cart {
            $cart = $this->lockedCartFor($user);
            $cart->load('items.purchasable');

            foreach ($cart->items as $item) {
                $this->inventory->release(
                    $this->inventoryFor($item->purchasable),
                    $item->quantity,
                    $user,
                    $this->reservationMetadata($cart),
                );
                $item->delete();
            }

            $this->refreshExpiry($cart);

            return $cart->refresh()->load('items.purchasable');
        });
    }

    private function cartFor(User $user): Cart
    {
        return Cart::query()->firstOrCreate(
            ['user_id' => $user->getKey()],
            ['expires_at' => now()->addDays(7)],
        );
    }

    private function lockedCartFor(User $user): Cart
    {
        $cart = $this->cartFor($user);

        return Cart::query()->lockForUpdate()->findOrFail($cart->getKey());
    }

    private function ownedItem(Cart $cart, int $itemId): CartItem
    {
        return $cart->items()
            ->with('purchasable')
            ->lockForUpdate()
            ->findOrFail($itemId);
    }

    private function resolvePurchasable(array $attributes): Model
    {
        if (isset($attributes['variant_id'])) {
            $variant = ProductVariant::query()->with('product')->findOrFail($attributes['variant_id']);

            abort_unless($variant->is_active && $variant->product->is_active, 404);

            return $variant;
        }

        $product = Product::query()->findOrFail($attributes['product_id']);
        abort_unless($product->is_active, 404);

        return $product;
    }

    private function inventoryFor(Model $purchasable): InventoryItem
    {
        $inventory = InventoryItem::query()
            ->whereMorphedTo('stockable', $purchasable)
            ->first();

        if (! $inventory) {
            throw ValidationException::withMessages([
                'stock' => ['Inventory is not initialized for this item.'],
            ]);
        }

        return $inventory;
    }

    private function priceFor(Model $purchasable): string
    {
        if ($purchasable instanceof ProductVariant) {
            return $purchasable->price ?? $purchasable->product->price;
        }

        return $purchasable->price;
    }

    private function refreshExpiry(Cart $cart): void
    {
        $cart->update(['expires_at' => now()->addDays(7)]);
    }

    private function reservationMetadata(Cart $cart): array
    {
        return [
            'reason' => 'Cart reservation',
            'reference' => "cart:{$cart->getKey()}",
        ];
    }
}
