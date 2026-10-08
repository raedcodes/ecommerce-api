<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(Request $request, CartService $carts): CartResource
    {
        return new CartResource($carts->currentCart($request->user()));
    }
}
