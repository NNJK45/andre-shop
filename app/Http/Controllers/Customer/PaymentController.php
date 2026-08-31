<?php

namespace App\Http\Controllers\Customer;

use App\Application\Payment\PaymentService;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Models\Payment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\InitializePaymentRequest;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentController extends Controller
{
    public function store(
        InitializePaymentRequest $request,
        string $order,
        PaymentService $payments,
    ): JsonResponse {
        $order = Order::query()
            ->where('user_id', $request->user()->getKey())
            ->where('number', $order)
            ->firstOrFail();

        $payment = $payments->initialize(
            $order,
            $request->user(),
            $request->validated('idempotency_key'),
            $request->validated(),
        );

        return (new PaymentResource($payment))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, string $payment): PaymentResource
    {
        $payment = Payment::query()
            ->where('user_id', $request->user()->getKey())
            ->where('reference', $payment)
            ->with('order')
            ->firstOrFail();

        return new PaymentResource($payment);
    }
}
