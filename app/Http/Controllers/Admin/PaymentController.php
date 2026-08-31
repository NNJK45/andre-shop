<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Payment\Models\Payment;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PaymentResource::collection(
            Payment::query()->with('order')->latest()->paginate(),
        );
    }

    public function show(Payment $payment): PaymentResource
    {
        return new PaymentResource($payment->load(['order', 'events']));
    }
}
