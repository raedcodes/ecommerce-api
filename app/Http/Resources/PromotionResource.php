<?php

namespace App\Http\Resources;

use App\Enums\PromotionType;
use App\Models\Promotion;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin view of a promotion. `value` is a whole percentage for percentage codes and a
 * dollar amount (e.g. "10.00") for fixed codes, matching what the admin endpoints accept.
 *
 * @mixin Promotion
 */
class PromotionResource extends JsonResource
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
            'code' => $this->code,
            'type' => $this->type,
            'value' => $this->type === PromotionType::Percentage ? $this->value : Money::format($this->value),
            'min_cart_amount' => $this->min_cart_amount === null ? null : Money::format($this->min_cart_amount),
            'max_discount_amount' => $this->max_discount_amount === null ? null : Money::format($this->max_discount_amount),
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'usage_limit' => $this->usage_limit,
            'per_customer_limit' => $this->per_customer_limit,
            'times_used' => $this->times_used,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
