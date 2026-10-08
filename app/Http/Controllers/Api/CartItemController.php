<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CartItemController extends Controller
{
    public function __construct(private CartService $carts) {}

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
    public function store(StoreCartItemRequest $request): JsonResponse
    {
        $cart = $this->carts->addItem(
            $request->user(),
            Product::findOrFail($request->validated('product_id')),
            $request->integer('quantity'),
        );

        return (new CartResource($cart))->response()->setStatusCode(201);
    }

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
    public function update(UpdateCartItemRequest $request, string $item): CartResource
    {
        return new CartResource($this->carts->updateItem($request->user(), $item, $request->integer('quantity')));
    }

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
    public function destroy(Request $request, string $item): CartResource
    {
        return new CartResource($this->carts->removeItem($request->user(), $item));
    }
}
