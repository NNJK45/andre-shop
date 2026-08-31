<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'on_hand_delta' => $this->on_hand_delta,
            'reserved_delta' => $this->reserved_delta,
            'reason' => $this->reason,
            'reference' => $this->reference,
            'performed_by_user_id' => $this->performed_by_user_id,
            'created_at' => $this->created_at,
        ];
    }
}
