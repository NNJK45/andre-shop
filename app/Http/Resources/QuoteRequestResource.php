<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuoteRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'total' => $this->total,
            'notes' => $this->notes,
            'requested_at' => $this->requested_at,
            'responded_at' => $this->responded_at,
            'valid_until' => $this->valid_until?->toDateString(),
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'requester' => new UserResource($this->whenLoaded('requester')),
            'items' => QuoteRequestItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
