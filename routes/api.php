<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register')->name('register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('logout');
});

Route::get('products', [ProductController::class, 'index'])->name('products.index');
Route::get('products/{product}', [ProductController::class, 'show'])
    ->whereNumber('product')
    ->name('products.show');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('cart', [CartController::class, 'show'])->name('cart.show');
    
    Route::post('cart/items', [CartController::class, 'addItem'])->name('cart.items.store');
    Route::match(['put', 'patch'], 'cart/items/{item}', [CartController::class, 'updateItem'])
        ->whereNumber('item')
        ->name('cart.items.update');
    Route::delete('cart/items/{item}', [CartController::class, 'removeItem'])
        ->whereNumber('item')
        ->name('cart.items.destroy');

    Route::post('cart/promotion', [CartController::class, 'applyPromotion'])->name('cart.promotion.store');
    Route::delete('cart/promotion', [CartController::class, 'removePromotion'])->name('cart.promotion.destroy');

    Route::post('checkout', [OrderController::class, 'checkout'])->name('checkout');

    Route::apiResource('orders', OrderController::class)
        ->only(['index', 'show'])
        ->whereNumber('order');
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])
        ->whereNumber('order')
        ->name('orders.cancel');
});
