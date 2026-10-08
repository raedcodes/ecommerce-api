<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplyPromotionRequest;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CartPromotionController extends Controller
{
    public function __construct(private CartService $carts) {}

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
    public function store(ApplyPromotionRequest $request): CartResource
    {
        return new CartResource($this->carts->applyPromotion($request->user(), $request->validated('code')));
    }

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
    public function destroy(Request $request): CartResource
    {
        return new CartResource($this->carts->removePromotion($request->user()));
    }
}
