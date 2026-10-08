<?php

namespace App\Exceptions;

final class EmptyCartException extends ApiException
{
    public static function make(): self
    {
        return new self('Your cart is empty.', 'cart_empty', 422);
    }
}
