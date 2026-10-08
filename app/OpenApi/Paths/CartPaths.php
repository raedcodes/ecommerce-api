<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

/**
 * OpenAPI operations for the cart, its items and its promotional code.
 *
 * Documentation only; the handlers are App\Http\Controllers\Api\CartController, App\Http\Controllers\Api\CartItemController, App\Http\Controllers\Api\CartPromotionController.
 * tests/Feature/ApiDocumentationTest.php fails if a route is missing here or a documented route no longer exists.
 */
final class CartPaths
{
    #[OA\Get(
        path: '/api/cart',
        operationId: 'showCart',
        description: 'Read-only: a customer without a cart gets an empty one. The applied promotion is re-evaluated; if it is no longer valid it is reported with its reason and not discounted.',
        summary: 'Show the cart',
        security: [['sanctum' => []]],
        tags: ['Cart'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The cart priced at current product prices.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Cart')],
                ),
            ),
            new OA\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function showCart(): void {}

    #[OA\Post(
        path: '/api/cart/items',
        operationId: 'addCartItem',
        description: 'Adding a product that is already in the cart increases its quantity. Stock is checked but not reserved.',
        summary: 'Add a product to the cart',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['product_id', 'quantity'],
                properties: [
                    new OA\Property(property: 'product_id', type: 'integer', example: 1),
                    new OA\Property(property: 'quantity', type: 'integer', minimum: 1, example: 2),
                ],
            ),
        ),
        tags: ['Cart'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'The updated cart.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Cart')],
                ),
            ),
            new OA\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OA\Response(
                response: 422,
                description: 'Invalid input (`validation_failed`, e.g. an inactive or unknown product), or the quantity in the cart would exceed the available stock (`insufficient_stock`).',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/Error'),
                        new OA\Schema(ref: '#/components/schemas/ValidationError'),
                    ],
                    examples: [
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
                            example: 'validation_failed',
                            summary: 'validation_failed',
                            value: [
                                'message' => 'The quantity field must be at least 1.',
                                'code' => 'validation_failed',
                                'errors' => ['quantity' => ['The quantity field must be at least 1.']],
                            ],
                        ),
                    ],
                ),
            ),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function addCartItem(): void {}

    #[OA\Patch(
        path: '/api/cart/items/{item}',
        operationId: 'updateCartItem',
        description: 'Also available as PUT. Items belonging to other customers return 404 (`cart_item_not_found`).',
        summary: 'Change a cart item quantity',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['quantity'],
                properties: [new OA\Property(property: 'quantity', type: 'integer', minimum: 1, example: 3)],
            ),
        ),
        tags: ['Cart'],
        parameters: [
            new OA\Parameter(
                name: 'item',
                description: 'Cart item id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The updated cart.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Cart')],
                ),
            ),
            new OA\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
            new OA\Response(
                response: 422,
                description: 'Invalid quantity (`validation_failed`) or more than the available stock (`insufficient_stock`).',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/Error'),
                        new OA\Schema(ref: '#/components/schemas/ValidationError'),
                    ],
                    examples: [
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
                            example: 'validation_failed',
                            summary: 'validation_failed',
                            value: [
                                'message' => 'The quantity field must be at least 1.',
                                'code' => 'validation_failed',
                                'errors' => ['quantity' => ['The quantity field must be at least 1.']],
                            ],
                        ),
                    ],
                ),
            ),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function updateCartItem(): void {}

    #[OA\Delete(
        path: '/api/cart/items/{item}',
        operationId: 'removeCartItem',
        summary: 'Remove a cart item',
        security: [['sanctum' => []]],
        tags: ['Cart'],
        parameters: [
            new OA\Parameter(
                name: 'item',
                description: 'Cart item id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The updated cart.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Cart')],
                ),
            ),
            new OA\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function removeCartItem(): void {}

    #[OA\Post(
        path: '/api/cart/promotion',
        operationId: 'applyPromotion',
        description: 'Codes are matched ignoring case and surrounding spaces. Eligibility is checked again at checkout.',
        summary: 'Apply a promotional code',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [new OA\Property(property: 'code', type: 'string', maxLength: 50, example: 'SUMMER20')],
            ),
        ),
        tags: ['Cart'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The cart with the discount applied.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Cart')],
                ),
            ),
            new OA\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OA\Response(
                response: 422,
                description: 'The customer is not eligible: `coupon_invalid` (unknown, inactive or not started), `coupon_expired`, `coupon_usage_limit_reached`, `coupon_customer_limit_reached`, `coupon_minimum_not_met`; or `cart_empty`; or `validation_failed`.',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/Error'),
                        new OA\Schema(ref: '#/components/schemas/ValidationError'),
                    ],
                    examples: [
                        new OA\Examples(
                            example: 'coupon_invalid',
                            summary: 'coupon_invalid',
                            value: ['message' => 'The coupon code is invalid.', 'code' => 'coupon_invalid'],
                        ),
                        new OA\Examples(
                            example: 'coupon_expired',
                            summary: 'coupon_expired',
                            value: ['message' => 'This coupon has expired.', 'code' => 'coupon_expired'],
                        ),
                        new OA\Examples(
                            example: 'coupon_usage_limit_reached',
                            summary: 'coupon_usage_limit_reached',
                            value: [
                                'message' => 'This coupon has reached its usage limit.',
                                'code' => 'coupon_usage_limit_reached',
                            ],
                        ),
                        new OA\Examples(
                            example: 'coupon_customer_limit_reached',
                            summary: 'coupon_customer_limit_reached',
                            value: [
                                'message' => 'You have already used this coupon the maximum number of times.',
                                'code' => 'coupon_customer_limit_reached',
                            ],
                        ),
                        new OA\Examples(
                            example: 'coupon_minimum_not_met',
                            summary: 'coupon_minimum_not_met',
                            value: [
                                'message' => 'This coupon requires a minimum cart subtotal of 100.00.',
                                'code' => 'coupon_minimum_not_met',
                                'details' => ['minimum' => '100.00', 'subtotal' => '99.99'],
                            ],
                        ),
                        new OA\Examples(
                            example: 'cart_empty',
                            summary: 'cart_empty',
                            value: ['message' => 'Your cart is empty.', 'code' => 'cart_empty'],
                        ),
                    ],
                ),
            ),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function applyPromotion(): void {}

    #[OA\Delete(
        path: '/api/cart/promotion',
        operationId: 'removePromotion',
        summary: 'Remove the promotional code',
        security: [['sanctum' => []]],
        tags: ['Cart'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The cart without a promotion.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Cart')],
                ),
            ),
            new OA\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function removePromotion(): void {}
}
