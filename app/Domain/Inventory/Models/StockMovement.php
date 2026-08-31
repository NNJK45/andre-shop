<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\StockMovementType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'performed_by_user_id',
        'type',
        'on_hand_delta',
        'reserved_delta',
        'reason',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'on_hand_delta' => 'integer',
            'reserved_delta' => 'integer',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
