<?php

namespace App\Application\Quote;

use App\Domain\Quote\Enums\QuoteStatus;
use App\Domain\Quote\Models\QuoteRequest;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuoteService
{
    public function create(array $attributes, User $requester): QuoteRequest
    {
        return DB::transaction(function () use ($attributes, $requester): QuoteRequest {
            $items = Arr::pull($attributes, 'items', []);
            $quote = QuoteRequest::query()->create([
                'supplier_id' => $attributes['supplier_id'],
                'requested_by_user_id' => $requester->getKey(),
                'reference' => $this->reference(),
                'status' => QuoteStatus::Draft,
                'currency' => $attributes['currency'] ?? 'XAF',
                'notes' => $attributes['notes'] ?? null,
                'requested_at' => now(),
                'valid_until' => $attributes['valid_until'] ?? null,
            ]);

            foreach ($items as $item) {
                $quote->items()->create($this->itemAttributes($item));
            }

            return $this->recalculate($quote);
        });
    }

    public function transition(QuoteRequest $quote, QuoteStatus $status): QuoteRequest
    {
        return DB::transaction(function () use ($quote, $status): QuoteRequest {
            $locked = QuoteRequest::query()->lockForUpdate()->findOrFail($quote->getKey());

            if (! $locked->status->canTransitionTo($status)) {
                throw ValidationException::withMessages([
                    'status' => ["Cannot transition a quote from {$locked->status->value} to {$status->value}."],
                ]);
            }

            $locked->update([
                'status' => $status,
                'responded_at' => in_array($status, [QuoteStatus::Received, QuoteStatus::Accepted, QuoteStatus::Rejected], true)
                    ? ($locked->responded_at ?? now())
                    : $locked->responded_at,
            ]);

            return $locked->refresh()->load(['supplier', 'requester', 'items.product', 'items.productVariant']);
        });
    }

    private function recalculate(QuoteRequest $quote): QuoteRequest
    {
        $subtotal = 0.0;

        foreach ($quote->items as $item) {
            $lineTotal = $item->quoted_unit_price === null ? 0.0 : (float) $item->quoted_unit_price * $item->quantity;
            $item->update(['total' => $lineTotal]);
            $subtotal += $lineTotal;
        }

        $quote->update(['subtotal' => $subtotal, 'total' => $subtotal]);

        return $quote->refresh()->load(['supplier', 'requester', 'items.product', 'items.productVariant']);
    }

    private function itemAttributes(array $item): array
    {
        return [
            'product_id' => $item['product_id'] ?? null,
            'product_variant_id' => $item['product_variant_id'] ?? null,
            'description' => $item['description'],
            'sku' => $item['sku'] ?? null,
            'quantity' => $item['quantity'],
            'quoted_unit_price' => $item['quoted_unit_price'] ?? null,
            'notes' => $item['notes'] ?? null,
        ];
    }

    private function reference(): string
    {
        do {
            $reference = 'RFQ-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
        } while (QuoteRequest::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
