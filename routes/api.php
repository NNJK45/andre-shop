<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->as('auth.')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    });
});

Route::prefix('admin')
    ->as('admin.')
    ->group(base_path('routes/admin.php'));

Route::prefix('customer')
    ->as('customer.')
    ->group(base_path('routes/customer.php'));

Route::prefix('webhooks')
    ->as('webhooks.')
    ->group(base_path('routes/webhook.php'));
