<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Products are never hard-deleted (order history links to them); deactivate via `status` instead.
 */
class ProductController extends Controller
{
    /**
     * All products, including inactive ones, newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100']
        ]);

        $products = Product::query()
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return ProductResource::collection($products);
    }

    public function store(ProductRequest $request): ProductResource
    {
        $validated = $request->productAttributes();
        $product = Product::create($validated);

        return new ProductResource($product);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }

    public function update(ProductRequest $request, Product $product): ProductResource
    {
        $validated = $request->productAttributes();
        $product->update($validated);

        return new ProductResource($product);
    }
}
