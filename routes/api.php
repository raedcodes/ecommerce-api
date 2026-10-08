<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CartItemController;
use App\Http\Controllers\Api\CartPromotionController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register')->name('register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('logout');
});

Route::get('products', [ProductController::class, 'index'])->name('products.index');
Route::get('products/{product}', [ProductController::class, 'show'])->whereNumber('product')->name('products.show');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('cart', [CartController::class, 'show'])->name('cart.show');

    Route::apiResource('cart/items', CartItemController::class)
        ->only(['store', 'update', 'destroy'])
        ->names('cart.items')
        ->where(['item' => '[0-9]+']);
        
    Route::apiSingleton('cart/promotion', CartPromotionController::class)
        ->creatable()
        ->only(['store', 'destroy'])
        ->names('cart.promotion');
});
