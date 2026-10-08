<?php

namespace App\Services;

use App\Exceptions\CouponException;
use App\Models\Cart;

/**
 * A cart priced at current product prices, with its applied promotion evaluated.
 */
final readonly class CartSummary
{
    public function __construct(
        public Cart $cart,
        public int $subtotal,
        public int $discount,
        public ?CouponException $promotionError = null,
    ) {}

    public function total(): int
    {
        return $this->subtotal - $this->discount;
    }
}
