<?php

namespace App\Http\Controllers\Admin;

use App\Application\Order\OrderStatusService;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return OrderResource::collection(
            Order::query()->with(['user', 'items'])->latest('placed_at')->paginate(),
        );
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(['user', 'items', 'statusHistory']));
    }

    public function updateStatus(
        UpdateOrderStatusRequest $request,
        Order $order,
        OrderStatusService $orders,
    ): OrderResource {
        $validated = $request->validated();

        return new OrderResource(
            $orders->transition(
                $order,
                OrderStatus::from($validated['status']),
                $request->user(),
                $validated['note'] ?? null,
            ),
        );
    }
}
