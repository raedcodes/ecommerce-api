<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplyPromotionRequest;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartPromotionController extends Controller
{
    public function store(ApplyPromotionRequest $request, CartService $carts): CartResource
    {
        return new CartResource($carts->applyPromotion($request->user(), $request->validated('code')));
    }

    public function destroy(Request $request, CartService $carts): CartResource
    {
        return new CartResource($carts->removePromotion($request->user()));
    }
}
