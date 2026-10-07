<?php

namespace App\Enums;

enum PromotionType: string
{
    /** Value is a whole percentage (1–100) of the cart subtotal. */
    case Percentage = 'percentage';

    /** Value is a fixed amount in cents. */
    case Fixed = 'fixed';
}
