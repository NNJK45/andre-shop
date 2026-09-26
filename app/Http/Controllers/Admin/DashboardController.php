<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Enums\PaymentStatus;
use App\Domain\Payment\Models\Payment;
use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryItemResource;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $lowStockQuery = InventoryItem::query()
            ->whereRaw('(on_hand - reserved) <= reorder_level');

        $recentOrders = Order::query()
            ->with('user')
            ->latest('placed_at')
            ->limit(5)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'number' => $order->number,
                'status' => $order->status->value,
                'total' => $order->total,
                'placed_at' => $order->placed_at,
                'customer_name' => $order->user?->name ?? 'Client',
            ]);

        return response()->json([
            'data' => [
                'active_products' => Product::query()->where('is_active', true)->count(),
                'orders' => Order::query()->count(),
                'confirmed_payments_total' => Payment::query()
                    ->where('status', PaymentStatus::Succeeded)
                    ->sum('amount'),
                'low_stock_count' => (clone $lowStockQuery)->count(),
                'recent_orders' => $recentOrders,
                'low_stock_items' => InventoryItemResource::collection(
                    (clone $lowStockQuery)->with('stockable')->orderBy('on_hand')->limit(5)->get(),
                ),
            ],
        ]);
    }
}
