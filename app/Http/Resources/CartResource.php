<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $total = $this->items->sum(
            fn ($item): float => (float) $item->unit_price * $item->quantity,
        );

        return [
            'id' => $this->id,
            'items' => CartItemResource::collection($this->items),
            'items_count' => $this->items->sum('quantity'),
            'total' => number_format($total, 2, '.', ''),
            'expires_at' => $this->expires_at,
        ];
    }
}
