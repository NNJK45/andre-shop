<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'checkout_url' => $this->checkout_url,
            'paid_at' => $this->paid_at,
            'failed_at' => $this->failed_at,
            'order' => new OrderResource($this->whenLoaded('order')),
            'created_at' => $this->created_at,
        ];
    }
}
