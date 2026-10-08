<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductCatalog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
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
    public function show(string $product): ProductResource
    {
        return new ProductResource(Product::query()->active()->findOrFail($product));
    }
}
