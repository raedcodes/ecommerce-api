<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CartController extends Controller
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
    public function show(Request $request, CartService $carts): CartResource
    {
        return new CartResource($carts->currentCart($request->user()));
    }
}
