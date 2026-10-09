<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplyPromotionRequest;
use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The authenticated customer's cart: its items and its promotional code.
 */
class CartController extends Controller
{
    public function __construct(private CartService $carts) {}

    public function show(Request $request): CartResource
    {
        return new CartResource($this->carts->currentCart($request->user()));
    }

    public function addItem(StoreCartItemRequest $request): JsonResponse
    {
        $cart = $this->carts->addItem(
            $request->user(),
            Product::findOrFail($request->validated('product_id')),
            $request->integer('quantity'),
        );

        return (new CartResource($cart))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function updateItem(UpdateCartItemRequest $request, string $item): CartResource
    {
        return new CartResource($this->carts->updateItem($request->user(), $item, $request->integer('quantity')));
    }

    public function removeItem(Request $request, string $item): CartResource
    {
        return new CartResource($this->carts->removeItem($request->user(), $item));
    }

    public function applyPromotion(ApplyPromotionRequest $request): CartResource
    {
        return new CartResource($this->carts->applyPromotion($request->user(), $request->validated('code')));
    }

    public function removePromotion(Request $request): CartResource
    {
        return new CartResource($this->carts->removePromotion($request->user()));
    }
}
