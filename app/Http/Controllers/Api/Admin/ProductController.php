<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

/**
 * Products are never hard-deleted (order history links to them); deactivate via `status` instead.
 */
class ProductController extends Controller
{
    /**
     * All products, including inactive ones, newest first.
     */
    #[OA\Get(
        path: '/api/admin/products',
        operationId: 'adminListProducts',
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
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return ProductResource::collection(
            Product::query()->orderByDesc('id')->paginate($validated['per_page'] ?? 15)->withQueryString()
        );
    }

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
    public function store(ProductRequest $request): ProductResource
    {
        return new ProductResource(Product::create($request->productAttributes()));
    }

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
    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }

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
    public function update(ProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->productAttributes());

        return new ProductResource($product);
    }
}
