<?php

namespace App\Http\Resources;

use App\Models\Cart;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Prices are always the products' current prices; nothing price-related is stored on the cart.
 *
 * @mixin Cart
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
        $subtotal = $this->subtotal();

        return [
            'items' => CartItemResource::collection($this->items),
            'item_count' => $this->items->sum('quantity'),
            'subtotal' => Money::format($subtotal),
            'total' => Money::format($subtotal),
        ];
    }
}
