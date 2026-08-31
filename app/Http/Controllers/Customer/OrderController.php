<?php

namespace App\Http\Controllers\Customer;

use App\Application\Order\CheckoutService;
use App\Domain\Order\Models\Order;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CheckoutRequest;
use App\Http\Resources\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return OrderResource::collection(
            Order::query()
                ->where('user_id', $request->user()->getKey())
                ->with('items')
                ->latest('placed_at')
                ->paginate(),
        );
    }

    public function checkout(CheckoutRequest $request, CheckoutService $checkout): JsonResponse
    {
        $order = $checkout->checkout($request->user(), $request->validated());

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, string $order): OrderResource
    {
        $order = Order::query()
            ->where('user_id', $request->user()->getKey())
            ->where('number', $order)
            ->with(['items', 'statusHistory'])
            ->firstOrFail();

        return new OrderResource($order);
    }
}
