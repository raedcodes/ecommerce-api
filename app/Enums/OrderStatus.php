<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * Whether a customer may still cancel an order in this status.
     */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Pending, self::Processing], true);
    }
}
