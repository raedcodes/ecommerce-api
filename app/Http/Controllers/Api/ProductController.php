<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductCatalog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class ProductController extends Controller
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
    public function index(ProductIndexRequest $request, ProductCatalog $catalog): AnonymousResourceCollection
    {
        $products = $catalog
            ->paginate(
                $request->catalogQuery(),
                $request->integer('per_page', ProductIndexRequest::DEFAULT_PER_PAGE),
                $request->integer('page', 1),
            )
            ->appends($request->safe()->except('page'));

        return ProductResource::collection($products);
    }

    /**
     * Inactive products are hidden from customers, so they return 404 like missing ones.
     */
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
    public function show(string $product): ProductResource
    {
        return new ProductResource(Product::query()->active()->findOrFail($product));
    }
}
