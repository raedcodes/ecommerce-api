<?php

use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\PromotionController;
use Illuminate\Support\Facades\Route;

/*
| Admin API. Registered in bootstrap/app.php with the "api" middleware group, the "api/admin"
| prefix, the "admin." name prefix, and auth:sanctum + can:admin on every route.
*/

Route::apiResource('products', ProductController::class)
    ->except(['destroy'])
    ->where(['product' => '[0-9]+']);

Route::apiResource('promotions', PromotionController::class)
    ->where(['promotion' => '[0-9]+']);
