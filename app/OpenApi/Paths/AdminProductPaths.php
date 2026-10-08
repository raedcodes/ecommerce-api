<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

/**
 * OpenAPI operations for admin product management.
 *
 * Documentation only; the handlers are App\Http\Controllers\Api\Admin\\ProductController.
 * tests/Feature/ApiDocumentationTest.php fails if a route is missing here or a documented route no longer exists.
 */
final class AdminProductPaths
{
    #[OA\Get(
        path: '/api/admin/products',
        operationId: 'adminListProducts',
        description: 'All products, including inactive ones, newest first.',
        summary: 'List all products, including inactive',
        security: [['sanctum' => []]],
        tags: ['Admin: Products'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/PerPage'),
            new OA\Parameter(ref: '#/components/parameters/Page'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated products, newest first.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/Product'),
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
    public function adminListProducts(): void {}

    #[OA\Post(
        path: '/api/admin/products',
        operationId: 'adminCreateProduct',
        summary: 'Create a product',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ProductInput'),
        ),
        tags: ['Admin: Products'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Created.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')],
                ),
            ),
            new OA\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function adminCreateProduct(): void {}

    #[OA\Get(
        path: '/api/admin/products/{product}',
        operationId: 'adminShowProduct',
        summary: 'Show any product',
        security: [['sanctum' => []]],
        tags: ['Admin: Products'],
        parameters: [
            new OA\Parameter(
                name: 'product',
                description: 'Product id.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The product.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')],
                ),
            ),
            new OA\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function adminShowProduct(): void {}

    #[OA\Patch(
        path: '/api/admin/products/{product}',
        operationId: 'adminUpdateProduct',
        description: 'Also available as PUT. Send only the fields to change. Products are not deleted; set status to inactive instead.',
        summary: 'Update a product',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ProductInput'),
        ),
        tags: ['Admin: Products'],
        parameters: [
            new OA\Parameter(
                name: 'product',
                description: 'Product id.',
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
                    properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Product')],
                ),
            ),
            new OA\Response(ref: '#/components/responses/Unauthenticated', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
            new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function adminUpdateProduct(): void {}
}
