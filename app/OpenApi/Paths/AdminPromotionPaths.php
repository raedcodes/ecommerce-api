<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

/**
 * OpenAPI operations for admin promotion management.
 *
 * Documentation only; the handlers are App\Http\Controllers\Api\Admin\\PromotionController.
 * tests/Feature/ApiDocumentationTest.php fails if a route is missing here or a documented route no longer exists.
 */
final class AdminPromotionPaths
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
    public function adminListPromotions(): void {}

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
    public function adminCreatePromotion(): void {}

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
    public function adminShowPromotion(): void {}

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
    public function adminUpdatePromotion(): void {}

    #[OA\Delete(
        path: '/api/admin/promotions/{promotion}',
        operationId: 'adminDeletePromotion',
        description: 'Past orders keep their promotion_code snapshot; carts using the code simply lose it.',
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
    public function adminDeletePromotion(): void {}
}
