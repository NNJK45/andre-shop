<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteRequestItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'description' => $this->description,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'quoted_unit_price' => $this->quoted_unit_price,
            'total' => $this->total,
            'notes' => $this->notes,
            'product' => new ProductResource($this->whenLoaded('product')),
            'variant' => new ProductVariantResource($this->whenLoaded('productVariant')),
        ];
    }
}
