<?php

namespace App\Http\Controllers\Api;

use App\Actions\CancelOrder;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

class CancelOrderController extends Controller
{
    /**
     * Responds 200 with the cancelled order, including when it was already cancelled.
     */
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
    public function __invoke(Order $order, CancelOrder $cancelOrder): OrderResource
    {
        Gate::authorize('cancel', $order);

        return new OrderResource($cancelOrder->handle($order));
    }
}
