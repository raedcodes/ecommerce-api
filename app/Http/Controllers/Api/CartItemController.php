<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartItemController extends Controller
{
    public function store(StoreCartItemRequest $request, CartService $carts): JsonResponse
    {
        $cart = $carts->addItem(
            $request->user(),
            Product::findOrFail($request->validated('product_id')),
            $request->integer('quantity'),
        );

        return (new CartResource($cart))->response()->setStatusCode(201);
    }

    public function update(UpdateCartItemRequest $request, string $item, CartService $carts): CartResource
    {
        return new CartResource($carts->updateItem($request->user(), $item, $request->integer('quantity')));
    }

    public function destroy(Request $request, string $item, CartService $carts): CartResource
    {
        return new CartResource($carts->removeItem($request->user(), $item));
    }
}
