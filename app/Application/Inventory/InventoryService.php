<?php

namespace App\Application\Inventory;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Enums\StockMovementType;
use App\Domain\Inventory\Models\InventoryItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function initialize(array $attributes, User $user): InventoryItem
    {
        $stockable = $this->resolveStockable($attributes);

        if (InventoryItem::query()->whereMorphedTo('stockable', $stockable)->exists()) {
            throw ValidationException::withMessages([
                'stockable' => ['Inventory is already initialized for this item.'],
            ]);
        }

        return DB::transaction(function () use ($attributes, $stockable, $user): InventoryItem {
            $item = new InventoryItem([
                'on_hand' => $attributes['on_hand'] ?? 0,
                'reserved' => 0,
                'reorder_level' => $attributes['reorder_level'] ?? 0,
            ]);
            $item->stockable()->associate($stockable);
            $item->save();

            if ($item->on_hand > 0) {
                $this->record(
                    $item,
                    StockMovementType::Initial,
                    $item->on_hand,
                    0,
                    $user,
                    $attributes,
                );
            }

            return $item->load('stockable');
        });
    }

    public function receive(InventoryItem $item, int $quantity, User $user, array $metadata = []): InventoryItem
    {
        return $this->mutate($item, function (InventoryItem $locked) use ($quantity): array {
            return [$locked->on_hand + $quantity, $locked->reserved, StockMovementType::Receipt, $quantity, 0];
        }, $user, $metadata);
    }

    public function adjust(InventoryItem $item, int $quantity, User $user, array $metadata): InventoryItem
    {
        return $this->mutate($item, function (InventoryItem $locked) use ($quantity): array {
            $onHand = $locked->on_hand + $quantity;

            if ($onHand < $locked->reserved) {
                throw ValidationException::withMessages([
                    'quantity' => ['The adjustment cannot reduce stock below the reserved quantity.'],
                ]);
            }

            return [$onHand, $locked->reserved, StockMovementType::Adjustment, $quantity, 0];
        }, $user, $metadata);
    }

    public function reserve(InventoryItem $item, int $quantity, User $user, array $metadata = []): InventoryItem
    {
        return $this->mutate($item, function (InventoryItem $locked) use ($quantity): array {
            if ($quantity > $locked->available) {
                throw ValidationException::withMessages([
                    'quantity' => ['The requested quantity exceeds available stock.'],
                ]);
            }

            return [$locked->on_hand, $locked->reserved + $quantity, StockMovementType::Reservation, 0, $quantity];
        }, $user, $metadata);
    }

    public function release(InventoryItem $item, int $quantity, User $user, array $metadata = []): InventoryItem
    {
        return $this->mutate($item, function (InventoryItem $locked) use ($quantity): array {
            if ($quantity > $locked->reserved) {
                throw ValidationException::withMessages([
                    'quantity' => ['The released quantity exceeds reserved stock.'],
                ]);
            }

            return [$locked->on_hand, $locked->reserved - $quantity, StockMovementType::Release, 0, -$quantity];
        }, $user, $metadata);
    }

    public function sellReserved(InventoryItem $item, int $quantity, User $user, array $metadata = []): InventoryItem
    {
        return $this->mutate($item, function (InventoryItem $locked) use ($quantity): array {
            if ($quantity > $locked->reserved || $quantity > $locked->on_hand) {
                throw ValidationException::withMessages([
                    'quantity' => ['The reserved stock is no longer sufficient to complete this sale.'],
                ]);
            }

            return [
                $locked->on_hand - $quantity,
                $locked->reserved - $quantity,
                StockMovementType::Sale,
                -$quantity,
                -$quantity,
            ];
        }, $user, $metadata);
    }

    public function returnStock(InventoryItem $item, int $quantity, User $user, array $metadata = []): InventoryItem
    {
        return $this->mutate(
            $item,
            fn (InventoryItem $locked): array => [
                $locked->on_hand + $quantity,
                $locked->reserved,
                StockMovementType::Return,
                $quantity,
                0,
            ],
            $user,
            $metadata,
        );
    }

    private function mutate(
        InventoryItem $item,
        callable $mutation,
        User $user,
        array $metadata,
    ): InventoryItem {
        return DB::transaction(function () use ($item, $mutation, $user, $metadata): InventoryItem {
            $locked = InventoryItem::query()->lockForUpdate()->findOrFail($item->getKey());
            [$onHand, $reserved, $type, $onHandDelta, $reservedDelta] = $mutation($locked);

            $locked->update([
                'on_hand' => $onHand,
                'reserved' => $reserved,
            ]);

            $this->record($locked, $type, $onHandDelta, $reservedDelta, $user, $metadata);

            return $locked->refresh()->load('stockable');
        });
    }

    private function record(
        InventoryItem $item,
        StockMovementType $type,
        int $onHandDelta,
        int $reservedDelta,
        User $user,
        array $metadata,
    ): void {
        $item->movements()->create([
            'performed_by_user_id' => $user->getKey(),
            'type' => $type,
            'on_hand_delta' => $onHandDelta,
            'reserved_delta' => $reservedDelta,
            'reason' => $metadata['reason'] ?? null,
            'reference' => $metadata['reference'] ?? null,
        ]);
    }

    private function resolveStockable(array $attributes): Model
    {
        if (isset($attributes['variant_id'])) {
            return ProductVariant::query()->findOrFail($attributes['variant_id']);
        }

        return Product::query()->findOrFail($attributes['product_id']);
    }
}
