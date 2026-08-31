<?php

namespace App\Http\Controllers\Admin;

use App\Application\Delivery\DeliveryService;
use App\Domain\Delivery\Enums\DeliveryStatus;
use App\Domain\Delivery\Models\Delivery;
use App\Domain\Order\Models\Order;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateDeliveryRequest;
use App\Http\Requests\Admin\UpdateDeliveryStatusRequest;
use App\Http\Resources\DeliveryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DeliveryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return DeliveryResource::collection(
            Delivery::query()->with('order.user')->latest()->paginate(),
        );
    }

    public function store(CreateDeliveryRequest $request, DeliveryService $deliveries): JsonResponse
    {
        $order = Order::query()->with('user')->findOrFail($request->integer('order_id'));
        $delivery = $deliveries->create($order, $request->validated());

        return (new DeliveryResource($delivery->load('order')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Delivery $delivery): DeliveryResource
    {
        return new DeliveryResource($delivery->load('order'));
    }

    public function updateStatus(
        UpdateDeliveryStatusRequest $request,
        Delivery $delivery,
        DeliveryService $deliveries,
    ): DeliveryResource {
        return new DeliveryResource($deliveries->transition(
            $delivery,
            $request->enum('status', DeliveryStatus::class),
            $request->user(),
            $request->validated('failure_reason'),
        ));
    }
}
