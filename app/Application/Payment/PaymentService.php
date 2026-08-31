<?php

namespace App\Application\Payment;

use App\Application\Payment\Contracts\PaymentGateway;
use App\Application\Payment\DTO\PaymentStatusResult;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function initialize(
        Order $order,
        User $user,
        ?string $idempotencyKey = null,
        array $paymentData = [],
    ): Payment {
        return DB::transaction(function () use ($order, $user, $idempotencyKey, $paymentData): Payment {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if ($lockedOrder->user_id !== $user->getKey()) {
                abort(404);
            }

            if ($lockedOrder->status !== OrderStatus::PendingPayment) {
                throw ValidationException::withMessages([
                    'order' => ['Only orders awaiting payment can be paid.'],
                ]);
            }

            if ($idempotencyKey) {
                $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();

                if ($existing) {
                    if ($existing->order_id !== $lockedOrder->id || $existing->user_id !== $user->getKey()) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => ['This idempotency key is already used for another payment.'],
                        ]);
                    }

                    return $existing->load('order');
                }
            }

            $existingPending = Payment::query()
                ->where('order_id', $lockedOrder->id)
                ->where('status', PaymentStatus::Pending)
                ->latest()
                ->first();

            if ($existingPending) {
                return $existingPending->load('order');
            }

            $payment = Payment::query()->create([
                'order_id' => $lockedOrder->id,
                'user_id' => $user->getKey(),
                'reference' => $this->paymentReference(),
                'provider' => 'nokash',
                'idempotency_key' => $idempotencyKey,
                'status' => PaymentStatus::Pending,
                'amount' => $lockedOrder->total,
                'currency' => $lockedOrder->currency,
                'metadata' => $paymentData,
            ]);

            $initialization = $this->gateway->initialize($payment);
            $payment->update([
                'provider_reference' => $initialization->providerReference,
                'checkout_url' => $initialization->checkoutUrl,
                'metadata' => $initialization->metadata,
            ]);

            return $payment->refresh()->load('order');
        });
    }

    public function reconcile(Payment $payment, PaymentStatusResult $result): Payment
    {
        return DB::transaction(function () use ($payment, $result): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if (
                $locked->provider_reference !== $result->providerReference
                || $result->merchantReference !== $locked->reference
                || number_format((float) $result->amount, 2, '.', '') !== number_format((float) $locked->amount, 2, '.', '')
            ) {
                throw ValidationException::withMessages([
                    'payment' => ['The NoKash status does not match the local payment.'],
                ]);
            }

            if ($result->status === PaymentStatus::Succeeded) {
                $updated = $this->markSucceeded($locked, $result->providerReference);
            } elseif ($result->status === PaymentStatus::Failed) {
                $updated = $this->markFailed($locked);
            } elseif ($result->status === PaymentStatus::Cancelled) {
                $updated = $this->markCancelled($locked);
            } else {
                $updated = $locked->load('order');
            }

            $eventId = $result->providerReference.':'.$result->status->value;
            $locked->events()->firstOrCreate(
                ['provider' => 'nokash', 'event_id' => $eventId],
                [
                    'type' => $result->status->value,
                    'payload' => $result->payload,
                    'processed_at' => now(),
                ],
            );

            return $updated->refresh()->load('order');
        });
    }

    public function markSucceeded(Payment $payment, ?string $providerReference = null): Payment
    {
        return DB::transaction(function () use ($payment, $providerReference): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($locked->status === PaymentStatus::Succeeded) {
                return $locked->load('order');
            }

            if ($locked->status->isFinal()) {
                throw ValidationException::withMessages([
                    'payment' => ['A final payment cannot be marked as succeeded.'],
                ]);
            }

            $locked->update([
                'status' => PaymentStatus::Succeeded,
                'provider_reference' => $providerReference ?? $locked->provider_reference,
                'paid_at' => now(),
                'failed_at' => null,
            ]);

            $order = Order::query()->lockForUpdate()->findOrFail($locked->order_id);

            if ($order->status === OrderStatus::PendingPayment) {
                $order->update(['status' => OrderStatus::Paid]);
                $order->statusHistory()->create([
                    'changed_by_user_id' => null,
                    'from_status' => OrderStatus::PendingPayment,
                    'to_status' => OrderStatus::Paid,
                    'note' => 'Payment confirmed by provider.',
                ]);
            }

            return $locked->refresh()->load('order');
        });
    }

    public function markFailed(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($locked->status === PaymentStatus::Succeeded || $locked->status->isFinal()) {
                return $locked->load('order');
            }

            $locked->update([
                'status' => PaymentStatus::Failed,
                'failed_at' => now(),
            ]);

            return $locked->refresh()->load('order');
        });
    }

    public function markCancelled(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment): Payment {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($locked->status->isFinal()) {
                return $locked->load('order');
            }

            $locked->update([
                'status' => PaymentStatus::Cancelled,
                'failed_at' => now(),
            ]);

            return $locked->refresh()->load('order');
        });
    }

    private function paymentReference(): string
    {
        do {
            $reference = 'PAY-'.now()->format('Ymd').'-'.Str::upper(Str::random(10));
        } while (Payment::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
