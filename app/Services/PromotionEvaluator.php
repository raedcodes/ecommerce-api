<?php

namespace App\Services;

use App\Enums\PromotionType;
use App\Exceptions\CouponException;
use App\Models\Promotion;
use App\Models\User;

/**
 * Decides whether a customer may use a promotion and how much it takes off a subtotal (in cents).
 *
 * Checks run in a fixed order so the customer always gets the most fundamental reason first.
 */
class PromotionEvaluator
{
    /**
     * Validate eligibility and return the discount in cents.
     *
     * @throws CouponException
     */
    public function discountFor(Promotion $promotion, User $customer, int $subtotal): int
    {
        $this->ensureEligible($promotion, $customer, $subtotal);

        return $this->calculateDiscount($promotion, $subtotal);
    }

    /**
     * @throws CouponException
     */
    public function ensureEligible(Promotion $promotion, User $customer, int $subtotal): void
    {
        // Dates are stored to the second, so compare at that precision: a code ending at
        // 23:59:59 stays valid for the whole of that second (start and end are inclusive).
        $now = now()->startOfSecond();

        if (! $promotion->is_active) {
            throw CouponException::invalid();
        }

        if ($promotion->starts_at !== null && $promotion->starts_at->isAfter($now)) {
            throw CouponException::notStarted();
        }

        if ($promotion->ends_at !== null && $promotion->ends_at->isBefore($now)) {
            throw CouponException::expired();
        }

        if ($promotion->usage_limit !== null && $promotion->times_used >= $promotion->usage_limit) {
            throw CouponException::usageLimitReached();
        }

        if ($promotion->per_customer_limit !== null && $promotion->timesUsedBy($customer) >= $promotion->per_customer_limit) {
            throw CouponException::customerLimitReached();
        }

        if ($promotion->min_cart_amount !== null && $subtotal < $promotion->min_cart_amount) {
            throw CouponException::minimumNotMet($promotion->min_cart_amount, $subtotal);
        }
    }

    /**
     * Percentages round half up to the cent; the result is capped by the promotion's maximum
     * and never exceeds the subtotal, so a total can never go negative.
     */
    public function calculateDiscount(Promotion $promotion, int $subtotal): int
    {
        $discount = match ($promotion->type) {
            PromotionType::Percentage => intdiv($subtotal * $promotion->value + 50, 100),
            PromotionType::Fixed => $promotion->value,
        };

        if ($promotion->max_discount_amount !== null) {
            $discount = min($discount, $promotion->max_discount_amount);
        }

        return min($discount, $subtotal);
    }
}
