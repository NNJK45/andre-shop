<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::get('/', function () {
    return view('welcome');
});

Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '^(?!api(?:/|$)|sanctum(?:/|$)).*$');
