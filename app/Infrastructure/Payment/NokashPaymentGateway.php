<?php

namespace App\Infrastructure\Payment;

use App\Application\Payment\Contracts\PaymentGateway;
use App\Application\Payment\DTO\PaymentInitialization;
use App\Application\Payment\DTO\PaymentStatusResult;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NokashPaymentGateway implements PaymentGateway
{
    public function initialize(Payment $payment): PaymentInitialization
    {
        $metadata = $payment->metadata ?? [];
        $phone = $metadata['user_phone'] ?? null;
        $method = $metadata['payment_method'] ?? null;

        if (! $phone || ! in_array($method, ['MTN_MOMO', 'ORANGE_MONEY'], true)) {
            throw new RuntimeException('NoKash PayIn requires a Cameroon phone number and MTN_MOMO or ORANGE_MONEY.');
        }

        $payload = [
            'i_space_key' => config('payments.nokash.i_space_key'),
            'app_space_key' => config('payments.nokash.app_space_key'),
            'payment_type' => 'CM_MOBILEMONEY',
            'country' => 'CM',
            'payment_method' => $method,
            'order_id' => $payment->reference,
            'amount' => (float) $payment->amount,
            'user_data' => [
                'user_phone' => $phone,
            ],
        ];

        if ($callback = config('payments.nokash.callback_url')) {
            $payload['callback_url'] = $callback;
        }

        $response = Http::asJson()
            ->acceptJson()
            ->timeout(config('payments.nokash.timeout_seconds'))
            ->post(config('payments.nokash.payin_url'), $payload);

        if ($response->failed()) {
            throw new RuntimeException('NoKash PayIn HTTP request failed: '.$response->body());
        }

        $body = $response->json();

        if (($body['status'] ?? null) !== 'REQUEST_OK' || ! is_array($body['data'] ?? null)) {
            throw new RuntimeException('NoKash rejected the PayIn request: '.($body['message'] ?? 'Unknown error.'));
        }

        $data = $body['data'];

        return new PaymentInitialization(
            providerReference: (string) $data['id'],
            metadata: array_merge($metadata, [
                'nokash' => $body,
                'nokash_status' => $data['status'] ?? null,
            ]),
        );
    }

    public function status(Payment $payment): PaymentStatusResult
    {
        $response = Http::asJson()
            ->acceptJson()
            ->timeout(config('payments.nokash.timeout_seconds'))
            ->post(config('payments.nokash.status_url'), [
                'transaction_id' => $payment->provider_reference,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('NoKash status request failed: '.$response->body());
        }

        $body = $response->json();

        if (($body['status'] ?? null) !== 'REQUEST_OK' || ! is_array($body['data'] ?? null)) {
            throw new RuntimeException('NoKash status request was rejected: '.($body['message'] ?? 'Unknown error.'));
        }

        $data = $body['data'];

        return new PaymentStatusResult(
            providerReference: (string) $data['id'],
            merchantReference: (string) ($data['orderId'] ?? ''),
            status: $this->mapStatus((string) ($data['status'] ?? '')),
            amount: (string) $data['amount'],
            payload: $body,
        );
    }

    private function mapStatus(string $status): PaymentStatus
    {
        return match (strtoupper($status)) {
            'PENDING' => PaymentStatus::Pending,
            'SUCCESS' => PaymentStatus::Succeeded,
            'FAILED', 'TIMEOUT' => PaymentStatus::Failed,
            'CANCELED' => PaymentStatus::Cancelled,
            default => throw new RuntimeException('Unknown NoKash payment status: '.$status),
        };
    }
}
