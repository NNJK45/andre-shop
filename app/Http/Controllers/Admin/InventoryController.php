<?php

namespace App\Http\Controllers\Admin;

use App\Application\Inventory\InventoryService;
use App\Domain\Inventory\Models\InventoryItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustStockRequest;
use App\Http\Requests\Admin\InitializeInventoryRequest;
use App\Http\Requests\Admin\StockQuantityRequest;
use App\Http\Requests\Admin\UpdateInventoryRequest;
use App\Http\Resources\InventoryItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class InventoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return InventoryItemResource::collection(
            InventoryItem::query()->with('stockable')->latest()->paginate(),
        );
    }

    public function lowStock(): AnonymousResourceCollection
    {
        return InventoryItemResource::collection(
            InventoryItem::query()
                ->with('stockable')
                ->whereRaw('(on_hand - reserved) <= reorder_level')
                ->orderByRaw('(on_hand - reserved) asc')
                ->paginate(),
        );
    }

    public function store(
        InitializeInventoryRequest $request,
        InventoryService $inventory,
    ): JsonResponse {
        $item = $inventory->initialize($request->validated(), $request->user());

        return (new InventoryItemResource($item))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(InventoryItem $inventoryItem): InventoryItemResource
    {
        $inventoryItem->load([
            'stockable',
            'movements' => fn ($query) => $query->latest()->limit(50),
        ]);

        return new InventoryItemResource($inventoryItem);
    }

    public function update(
        UpdateInventoryRequest $request,
        InventoryItem $inventoryItem,
    ): InventoryItemResource {
        $inventoryItem->update($request->validated());

        return new InventoryItemResource($inventoryItem->refresh()->load('stockable'));
    }

    public function receive(
        StockQuantityRequest $request,
        InventoryItem $inventoryItem,
        InventoryService $inventory,
    ): InventoryItemResource {
        return new InventoryItemResource(
            $inventory->receive(
                $inventoryItem,
                $request->integer('quantity'),
                $request->user(),
                Arr::except($request->validated(), 'quantity'),
            ),
        );
    }

    public function adjust(
        AdjustStockRequest $request,
        InventoryItem $inventoryItem,
        InventoryService $inventory,
    ): InventoryItemResource {
        return new InventoryItemResource(
            $inventory->adjust(
                $inventoryItem,
                $request->integer('quantity'),
                $request->user(),
                Arr::except($request->validated(), 'quantity'),
            ),
        );
    }

    public function reserve(
        StockQuantityRequest $request,
        InventoryItem $inventoryItem,
        InventoryService $inventory,
    ): InventoryItemResource {
        return new InventoryItemResource(
            $inventory->reserve(
                $inventoryItem,
                $request->integer('quantity'),
                $request->user(),
                Arr::except($request->validated(), 'quantity'),
            ),
        );
    }

    public function release(
        StockQuantityRequest $request,
        InventoryItem $inventoryItem,
        InventoryService $inventory,
    ): InventoryItemResource {
        return new InventoryItemResource(
            $inventory->release(
                $inventoryItem,
                $request->integer('quantity'),
                $request->user(),
                Arr::except($request->validated(), 'quantity'),
            ),
        );
    }
}
