<?php

namespace App\Http\Controllers\Api;

use App\Actions\PlaceOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;

class CheckoutController extends Controller
{
    /**
     * Responds 201 for a new order and 200 when an Idempotency-Key replays an existing one.
     */
    public function __invoke(CheckoutRequest $request, PlaceOrder $placeOrder): OrderResource
    {
        return new OrderResource($placeOrder->handle($request->user(), $request->validated('idempotency_key')));
    }
}
