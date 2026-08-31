<?php

namespace App\Http\Controllers\Webhook;

use App\Application\Payment\Contracts\PaymentGateway;
use App\Application\Payment\PaymentService;
use App\Domain\Payment\Models\Payment;
use App\Http\Requests\Webhook\NokashCallbackRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class NokashWebhookController
{
    public function __invoke(
        NokashCallbackRequest $request,
        PaymentGateway $gateway,
        PaymentService $payments,
    ): JsonResponse {
        $callback = $request->validated();
        $payment = Payment::query()
            ->where('provider_reference', $callback['id'])
            ->where('reference', $callback['orderId'])
            ->firstOrFail();

        $verified = $gateway->status($payment);

        if (
            $verified->providerReference !== $callback['id']
            || $verified->merchantReference !== $callback['orderId']
            || number_format((float) $verified->amount, 2, '.', '') !== number_format((float) $callback['amount'], 2, '.', '')
        ) {
            throw ValidationException::withMessages([
                'callback' => ['The NoKash callback could not be verified.'],
            ]);
        }

        $payment = $payments->reconcile($payment, $verified);

        return response()->json([
            'status' => 'accepted',
            'payment_status' => $payment->status->value,
        ]);
    }
}
