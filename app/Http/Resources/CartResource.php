<?php

namespace App\Http\Resources;

use App\Services\CartSummary;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Prices are always the products' current prices; nothing price-related is stored on the cart.
 *
 * @property CartSummary $resource
 */
class CartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $summary = $this->resource;
        $cart = $summary->cart;

        return [
            'items' => CartItemResource::collection($cart->items),
            'item_count' => $cart->items->sum('quantity'),
            'subtotal' => Money::format($summary->subtotal),
            'promotion' => $cart->promotion === null ? null : [
                'code' => $cart->promotion->code,
                'type' => $cart->promotion->type,
                'is_valid' => $summary->promotionError === null,
                'error' => $summary->promotionError === null ? null : [
                    'code' => $summary->promotionError->errorCode,
                    'message' => $summary->promotionError->getMessage(),
                ],
            ],
            'discount' => Money::format($summary->discount),
            'total' => Money::format($summary->total()),
        ];
    }
}
