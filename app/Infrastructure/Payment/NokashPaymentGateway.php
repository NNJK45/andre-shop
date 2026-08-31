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

        if (! is_string($phone) || ! preg_match('/^237[0-9]{9}$/', $phone) || ! in_array($method, ['MTN_MOMO', 'ORANGE_MONEY'], true)) {
            throw new RuntimeException('NoKash PayIn requires a Cameroon phone number and MTN_MOMO or ORANGE_MONEY.');
        }

        $this->ensureConfiguration();

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
            providerReference: $this->requiredString($data, 'id', 'PayIn'),
            metadata: array_merge($metadata, [
                'nokash' => $body,
                'nokash_status' => $data['status'] ?? null,
            ]),
        );
    }

    public function status(Payment $payment): PaymentStatusResult
    {
        $this->ensureConfiguration();

        if (! $payment->provider_reference) {
            throw new RuntimeException('NoKash status check requires a provider reference.');
        }

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
            providerReference: $this->requiredString($data, 'id', 'status'),
            merchantReference: $this->requiredString($data, 'orderId', 'status'),
            status: $this->mapStatus((string) ($data['status'] ?? '')),
            amount: $this->requiredString($data, 'amount', 'status'),
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

    private function ensureConfiguration(): void
    {
        foreach (['payin_url', 'status_url', 'i_space_key', 'app_space_key'] as $key) {
            $value = config("payments.nokash.{$key}");

            if (! is_string($value) || trim($value) === '') {
                throw new RuntimeException("NoKash configuration is incomplete: {$key} is missing.");
            }
        }
    }

    private function requiredString(array $data, string $key, string $operation): string
    {
        if (! array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
            throw new RuntimeException("NoKash {$operation} response is missing {$key}.");
        }

        return (string) $data[$key];
    }
}
