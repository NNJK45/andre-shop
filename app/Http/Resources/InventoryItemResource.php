<?php

namespace App\Http\Resources;

use App\Domain\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stockable_type' => $this->stockable instanceof Product ? 'product' : 'variant',
            'stockable_id' => $this->stockable_id,
            'sku' => $this->stockable?->sku,
            'name' => $this->stockable?->name,
            'on_hand' => $this->on_hand,
            'reserved' => $this->reserved,
            'available' => $this->available,
            'reorder_level' => $this->reorder_level,
            'is_low_stock' => $this->is_low_stock,
            'movements' => StockMovementResource::collection($this->whenLoaded('movements')),
            'updated_at' => $this->updated_at,
        ];
    }
}
