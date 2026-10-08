<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromotionRequest;
use App\Http\Resources\PromotionResource;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class PromotionController extends Controller
{
    #[OA\Get(
        path: '/api/admin/promotions',
        operationId: 'adminListPromotions',
        summary: 'List promotions',
        security: [['sanctum' => []]],
        tags: ['Admin: Promotions'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/PerPage'),
            new OA\Parameter(ref: '#/components/parameters/Page'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated promotions, newest first.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Promotion'),
                        ),
                        new OA\Property(property: 'links', ref: '#/components/schemas/PaginationLinks'),
                        new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                    ],
                ),
            ),
            new OA\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return PromotionResource::collection(
            Promotion::query()->orderByDesc('id')->paginate($validated['per_page'] ?? 15)->withQueryString()
        );
    }

    #[OA\Post(
        path: '/api/admin/promotions',
        operationId: 'adminCreatePromotion',
        summary: 'Create a promotion',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PromotionInput'),
        ),
        tags: ['Admin: Promotions'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Created.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Promotion')],
                ),
            ),
            new OA\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function store(PromotionRequest $request): PromotionResource
    {
        return new PromotionResource(Promotion::create($request->promotionAttributes()));
    }

    #[OA\Get(
        path: '/api/admin/promotions/{promotion}',
        operationId: 'adminShowPromotion',
        summary: 'Show a promotion',
        security: [['sanctum' => []]],
        tags: ['Admin: Promotions'],
        parameters: [
            new OA\Parameter(
                name: 'promotion',
                description: 'Promotion id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The promotion.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Promotion')],
                ),
            ),
            new OA\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function show(Promotion $promotion): PromotionResource
    {
        return new PromotionResource($promotion);
    }

    #[OA\Patch(
        path: '/api/admin/promotions/{promotion}',
        operationId: 'adminUpdatePromotion',
        description: 'Also available as PUT. Send only the fields to change; times_used cannot be set.',
        summary: 'Update a promotion',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/PromotionInput'),
        ),
        tags: ['Admin: Promotions'],
        parameters: [
            new OA\Parameter(
                name: 'promotion',
                description: 'Promotion id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Updated.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Promotion')],
                ),
            ),
            new OA\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
            new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function update(PromotionRequest $request, Promotion $promotion): PromotionResource
    {
        $promotion->update($request->promotionAttributes());

        return new PromotionResource($promotion);
    }

    /**
     * Past orders keep their promotion_code snapshot; carts using the code simply lose it.
     */
    #[OA\Delete(
        path: '/api/admin/promotions/{promotion}',
        operationId: 'adminDeletePromotion',
        summary: 'Delete a promotion',
        security: [['sanctum' => []]],
        tags: ['Admin: Promotions'],
        parameters: [
            new OA\Parameter(
                name: 'promotion',
                description: 'Promotion id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Deleted. Past orders keep the code they used.'),
            new OA\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function destroy(Promotion $promotion): Response
    {
        $promotion->delete();

        return response()->noContent();
    }
}
