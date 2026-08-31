<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DeliveryController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProductImageController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Admin\QuoteRequestController;
use App\Http\Controllers\Admin\SupplierController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:admin'])
    ->scopeBindings()
    ->group(function (): void {
        Route::apiResource('categories', CategoryController::class);
        Route::apiResource('products', ProductController::class);

        Route::post('products/{product}/variants', [ProductVariantController::class, 'store'])
            ->name('products.variants.store');
        Route::patch('products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])
            ->name('products.variants.update');
        Route::delete('products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])
            ->name('products.variants.destroy');

        Route::post('products/{product}/images', [ProductImageController::class, 'store'])
            ->name('products.images.store');
        Route::patch('products/{product}/images/{image}', [ProductImageController::class, 'update'])
            ->name('products.images.update');
        Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy'])
            ->name('products.images.destroy');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])
            ->name('orders.status.update');

        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');

        Route::apiResource('suppliers', SupplierController::class);
        Route::get('quote-requests', [QuoteRequestController::class, 'index'])->name('quote-requests.index');
        Route::post('quote-requests', [QuoteRequestController::class, 'store'])->name('quote-requests.store');
        Route::get('quote-requests/{quoteRequest}', [QuoteRequestController::class, 'show'])->name('quote-requests.show');
        Route::patch('quote-requests/{quoteRequest}/status', [QuoteRequestController::class, 'updateStatus'])
            ->name('quote-requests.status.update');

        Route::get('deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
        Route::post('deliveries', [DeliveryController::class, 'store'])->name('deliveries.store');
        Route::get('deliveries/{delivery}', [DeliveryController::class, 'show'])->name('deliveries.show');
        Route::patch('deliveries/{delivery}/status', [DeliveryController::class, 'updateStatus'])
            ->name('deliveries.status.update');

        Route::get('inventory/low-stock', [InventoryController::class, 'lowStock'])
            ->name('inventory.low-stock');
        Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('inventory', [InventoryController::class, 'store'])->name('inventory.store');
        Route::get('inventory/{inventoryItem}', [InventoryController::class, 'show'])->name('inventory.show');
        Route::patch('inventory/{inventoryItem}', [InventoryController::class, 'update'])->name('inventory.update');
        Route::post('inventory/{inventoryItem}/receive', [InventoryController::class, 'receive'])
            ->name('inventory.receive');
        Route::post('inventory/{inventoryItem}/adjust', [InventoryController::class, 'adjust'])
            ->name('inventory.adjust');
        Route::post('inventory/{inventoryItem}/reserve', [InventoryController::class, 'reserve'])
            ->name('inventory.reserve');
        Route::post('inventory/{inventoryItem}/release', [InventoryController::class, 'release'])
            ->name('inventory.release');
    });
