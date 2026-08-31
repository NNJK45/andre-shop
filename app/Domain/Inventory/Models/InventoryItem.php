<?php

namespace App\Domain\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'on_hand',
        'reserved',
        'reorder_level',
    ];

    protected $appends = [
        'available',
        'is_low_stock',
    ];

    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
            'reserved' => 'integer',
            'reorder_level' => 'integer',
        ];
    }

    public function stockable(): MorphTo
    {
        return $this->morphTo();
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getAvailableAttribute(): int
    {
        return $this->on_hand - $this->reserved;
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->available <= $this->reorder_level;
    }
}
