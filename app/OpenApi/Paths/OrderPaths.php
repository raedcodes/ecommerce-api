<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

/**
 * OpenAPI operations for checkout, order history and cancellation.
 *
 * Documentation only; the handlers are in App\Http\Controllers\Api\OrderController (index, checkout, show, cancel).
 * tests/Feature/ApiDocumentationTest.php fails if a route is missing here or a documented route no longer exists.
 */
final class OrderPaths
{
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
                description: 'Optional, up to 100 characters (letters, numbers, - _ : .), e.g. a UUID generated per checkout attempt. Retrying with the same key returns the original order with status 200; use a new key for each new checkout.',
                in: 'header',
                schema: new OA\Schema(type: 'string', maxLength: 100),
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
    public function checkout(): void {}

    #[OA\Get(
        path: '/api/orders',
        operationId: 'listOrders',
        description: 'The customer\'s own orders, newest first.',
        summary: 'List my orders',
        security: [['sanctum' => []]],
        tags: ['Orders'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/PerPage'),
            new OA\Parameter(ref: '#/components/parameters/Page'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Your orders, newest first.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Order'),
                        ),
                        new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
                        new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                    ],
                ),
            ),
            new OA\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function listOrders(): void {}

    #[OA\Get(
        path: '/api/orders/{order}',
        operationId: 'showOrder',
        description: 'Orders belonging to other customers return 403 (`forbidden`).',
        summary: 'Show one of my orders',
        security: [['sanctum' => []]],
        tags: ['Orders'],
        parameters: [
            new OA\Parameter(
                name: 'order',
                description: 'Order id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The order with its items.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Order')],
                ),
            ),
            new OA\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function showOrder(): void {}

    #[OA\Post(
        path: '/api/orders/{order}/cancel',
        operationId: 'cancelOrder',
        description: 'Allowed while pending or processing. Restores the purchased stock and gives back the coupon use. Safe to send more than once: stock is restored exactly once.',
        summary: 'Cancel an order',
        security: [['sanctum' => []]],
        tags: ['Orders'],
        parameters: [
            new OA\Parameter(
                name: 'order',
                description: 'Order id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The cancelled order. Repeating the request returns 200 and changes nothing.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Order')],
                ),
            ),
            new OA\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
            new OA\Response(
                response: 409,
                description: 'The order has shipped or been delivered (`invalid_order_status`).',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/Error',
                    example: [
                        'message' => 'Orders that are shipped can no longer be cancelled.',
                        'code' => 'invalid_order_status',
                        'details' => ['status' => 'shipped'],
                    ],
                ),
            ),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function cancelOrder(): void {}
}
