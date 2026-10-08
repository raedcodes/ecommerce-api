<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use Illuminate\Http\Response;

final class InvalidOrderStatusException extends ApiException
{
    public static function cannotCancel(OrderStatus $status): self
    {
        return new self(
            "Orders that are {$status->value} can no longer be cancelled.",
            'invalid_order_status',
            Response::HTTP_CONFLICT,
            ['status' => $status->value],
        );
    }
}
