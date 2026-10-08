<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Snapshot values from the time of purchase; later product changes do not affect them.
 *
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
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
            'name' => $this->product_name,
            'sku' => $this->product_sku,
            'unit_price' => Money::format($this->unit_price),
            'quantity' => $this->quantity,
            'line_total' => Money::format($this->line_total),
        ];
    }
}
