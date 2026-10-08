<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

/**
 * OpenAPI operations for the public product catalog.
 *
 * Documentation only; the handlers are App\Http\Controllers\Api\ProductController.
 * tests/Feature/ApiDocumentationTest.php fails if a route is missing here or a documented route no longer exists.
 */
final class ProductPaths
{
    #[OA\Get(
        path: '/api/products',
        operationId: 'listProducts',
        description: 'Filtering and sorting use the spatie/laravel-query-builder syntax. Unknown filters or sorts return 422. Results are cached per filter combination and refreshed whenever a product or its stock changes.',
        summary: 'List active products',
        tags: ['Products'],
        parameters: [
            new OA\Parameter(
                name: 'filter[name]',
                description: 'Case-insensitive partial name match; %, _ and commas are matched literally.',
                in: 'query',
                schema: new OA\Schema(type: 'string', maxLength: 100),
            ),
            new OA\Parameter(
                name: 'filter[min_price]',
                description: 'Minimum price in dollars (inclusive).',
                in: 'query',
                schema: new OA\Schema(type: 'string', example: '10.00'),
            ),
            new OA\Parameter(
                name: 'filter[max_price]',
                description: 'Maximum price in dollars (inclusive); must be at least min_price.',
                in: 'query',
                schema: new OA\Schema(type: 'string', example: '50.00'),
            ),
            new OA\Parameter(
                name: 'filter[in_stock]',
                description: 'true: stock above 0; false: out of stock.',
                in: 'query',
                schema: new OA\Schema(type: 'string', enum: ['true', 'false', '1', '0']),
            ),
            new OA\Parameter(
                name: 'sort',
                description: 'Prefix with - for descending. Default -created_at.',
                in: 'query',
                schema: new OA\Schema(
                    type: 'string',
                    enum: ['price', '-price', 'name', '-name', 'created_at', '-created_at'],
                ),
            ),
            new OA\Parameter(ref: '#/components/parameters/PerPage'),
            new OA\Parameter(ref: '#/components/parameters/Page'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated active products.',
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
            new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function listProducts(): void {}

    #[OA\Get(
        path: '/api/products/{product}',
        operationId: 'showProduct',
        description: 'Inactive products return 404 like missing ones.',
        summary: 'Show an active product',
        tags: ['Products'],
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
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function showProduct(): void {}
}
