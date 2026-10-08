<?php

namespace App\Http\Controllers\Api;

use App\Actions\PlaceOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use OpenApi\Attributes as OA;

class CheckoutController extends Controller
{
    /**
     * Responds 201 for a new order and 200 when an Idempotency-Key replays an existing one.
     */
    #[OA\Post(
        path: '/api/checkout',
        operationId: 'checkout',
        description: 'Takes no body: prices and totals are recalculated on the server from the locked product rows, and anything sent by the client is ignored. Runs in one database transaction with row locks, so concurrent checkouts can never oversell stock or a coupon. Send an Idempotency-Key header to make retries safe.',
        summary: 'Place an order from the cart',
        security: [['sanctum' => []]],
        tags: ['Orders'],
        parameters: [
            new OA\Parameter(
                name: 'Idempotency-Key',
                description: 'Optional, up to 100 characters (letters, numbers, - _ : .). Retrying with the same key returns the original order with status 200.',
                in: 'header',
                schema: new OA\Schema(type: 'string', maxLength: 100, example: 'checkout-6f1c2a'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Order placed.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Order')],
                ),
            ),
            new OA\Response(
                response: 200,
                description: 'Replay of an earlier request with the same Idempotency-Key; nothing new was placed.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Order')],
                ),
            ),
            new OA\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OA\Response(
                response: 422,
                description: 'Nothing was changed: `cart_empty`, `insufficient_stock` (lists every short item), a coupon error (`coupon_*`), or an invalid Idempotency-Key (`validation_failed`).',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/Error'),
                        new OA\Schema(ref: '#/components/schemas/ValidationError'),
                    ],
                    examples: [
                        new OA\Examples(
                            example: 'cart_empty',
                            summary: 'cart_empty',
                            value: ['message' => 'Your cart is empty.', 'code' => 'cart_empty'],
                        ),
                        new OA\Examples(
                            example: 'insufficient_stock',
                            summary: 'insufficient_stock',
                            value: [
                                'message' => "Only 3 units of 'Desk Lamp' are available.",
                                'code' => 'insufficient_stock',
                                'details' => [
                                    'items' => [
                                        ['product_id' => 1, 'name' => 'Desk Lamp', 'requested' => 4, 'available' => 3],
                                    ],
                                ],
                            ],
                        ),
                        new OA\Examples(
                            example: 'coupon_expired',
                            summary: 'coupon_expired',
                            value: ['message' => 'This coupon has expired.', 'code' => 'coupon_expired'],
                        ),
                    ],
                ),
            ),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function __invoke(CheckoutRequest $request, PlaceOrder $placeOrder): OrderResource
    {
        return new OrderResource($placeOrder->handle($request->user(), $request->validated('idempotency_key')));
    }
}
