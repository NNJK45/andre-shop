<?php

namespace App\Domain\Quote\Models;

use App\Domain\Quote\Enums\QuoteStatus;
use App\Domain\Supplier\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuoteRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'requested_by_user_id',
        'reference',
        'status',
        'currency',
        'notes',
        'requested_at',
        'responded_at',
        'valid_until',
        'subtotal',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'requested_at' => 'datetime',
            'responded_at' => 'datetime',
            'valid_until' => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteRequestItem::class);
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }
}
