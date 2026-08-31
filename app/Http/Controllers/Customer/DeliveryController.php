<?php

namespace App\Http\Controllers\Customer;

use App\Domain\Order\Models\Order;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryResource;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function show(Request $request, string $order): DeliveryResource
    {
        $order = Order::query()
            ->where('user_id', $request->user()->getKey())
            ->where('number', $order)
            ->firstOrFail();

        return new DeliveryResource($order->delivery()->with('order')->firstOrFail());
    }
}
