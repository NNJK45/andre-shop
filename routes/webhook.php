<?php

use App\Http\Controllers\Webhook\NokashWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('nokash', NokashWebhookController::class)->name('nokash');
