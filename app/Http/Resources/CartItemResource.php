<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->product->name,
            'sku' => $this->product->sku,
            'unit_price' => Money::format($this->product->price),
            'quantity' => $this->quantity,
            'line_total' => Money::format($this->lineTotal()),
            'available_quantity' => $this->product->availableQuantity(),
            'is_available' => $this->isAvailable(),
        ];
    }
}
