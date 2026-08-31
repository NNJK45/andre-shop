<?php

use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\CatalogController;
use App\Http\Controllers\Customer\DeliveryController;
use App\Http\Controllers\Customer\NotificationController;
use App\Http\Controllers\Customer\OrderController;
use App\Http\Controllers\Customer\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('catalog')->as('catalog.')->group(function (): void {
    Route::get('categories', [CatalogController::class, 'categories'])->name('categories.index');
    Route::get('products', [CatalogController::class, 'products'])->name('products.index');
    Route::get('products/{product}', [CatalogController::class, 'product'])->name('products.show');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('cart', [CartController::class, 'show'])->name('cart.show');
    Route::post('cart/items', [CartController::class, 'add'])->name('cart.items.add');
    Route::patch('cart/items/{cartItem}', [CartController::class, 'update'])->name('cart.items.update');
    Route::delete('cart/items/{cartItem}', [CartController::class, 'remove'])->name('cart.items.remove');
    Route::delete('cart', [CartController::class, 'clear'])->name('cart.clear');

    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('orders', [OrderController::class, 'checkout'])->name('orders.checkout');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/payments', [PaymentController::class, 'store'])
        ->name('orders.payments.store');
    Route::get('orders/{order}/delivery', [DeliveryController::class, 'show'])
        ->name('orders.delivery.show');

    Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read');
});
