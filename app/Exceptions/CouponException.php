<?php

namespace App\Exceptions;

use App\Support\Money;

/**
 * A promotional code the customer is not eligible to use right now.
 */
final class CouponException extends ApiException
{
    /**
     * Unknown and switched-off codes share one response so codes cannot be probed.
     */
    public static function invalid(): self
    {
        return new self('The coupon code is invalid.', 'coupon_invalid', 422);
    }

    public static function notStarted(): self
    {
        return new self('This coupon is not active yet.', 'coupon_invalid', 422);
    }

    public static function expired(): self
    {
        return new self('This coupon has expired.', 'coupon_expired', 422);
    }

    public static function usageLimitReached(): self
    {
        return new self('This coupon has reached its usage limit.', 'coupon_usage_limit_reached', 422);
    }

    public static function customerLimitReached(): self
    {
        return new self('You have already used this coupon the maximum number of times.', 'coupon_customer_limit_reached', 422);
    }

    public static function minimumNotMet(int $minimumCents, int $subtotalCents): self
    {
        return new self(
            'This coupon requires a minimum cart subtotal of '.Money::format($minimumCents).'.',
            'coupon_minimum_not_met',
            422,
            ['minimum' => Money::format($minimumCents), 'subtotal' => Money::format($subtotalCents)],
        );
    }
}
