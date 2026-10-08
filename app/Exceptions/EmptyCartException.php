<?php

namespace App\Exceptions;

use Illuminate\Http\Response;

final class EmptyCartException extends ApiException
{
    public static function make(): self
    {
        return new self('Your cart is empty.', 'cart_empty', Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
