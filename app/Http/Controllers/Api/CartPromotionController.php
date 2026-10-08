<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplyPromotionRequest;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartPromotionController extends Controller
{
    public function __construct(private CartService $carts) {}

    public function store(ApplyPromotionRequest $request): CartResource
    {
        return new CartResource($this->carts->applyPromotion($request->user(), $request->validated('code')));
    }

    public function destroy(Request $request): CartResource
    {
        return new CartResource($this->carts->removePromotion($request->user()));
    }
}
