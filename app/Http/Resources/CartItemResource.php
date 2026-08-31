<?php

namespace App\Http\Resources;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $product = $this->purchasable instanceof ProductVariant
            ? $this->purchasable->product
            : $this->purchasable;

        return [
            'id' => $this->id,
            'type' => $this->purchasable instanceof Product ? 'product' : 'variant',
            'purchasable_id' => $this->purchasable_id,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
            ],
            'variant' => $this->when(
                $this->purchasable instanceof ProductVariant,
                fn () => [
                    'id' => $this->purchasable->id,
                    'name' => $this->purchasable->name,
                    'attributes' => $this->purchasable->attributes,
                ],
            ),
            'sku' => $this->purchasable->sku,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'subtotal' => number_format((float) $this->unit_price * $this->quantity, 2, '.', ''),
        ];
    }
}
