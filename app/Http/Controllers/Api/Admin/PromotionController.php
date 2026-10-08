<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PromotionRequest;
use App\Http\Resources\PromotionResource;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PromotionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return PromotionResource::collection(
            Promotion::query()->orderByDesc('id')->paginate($validated['per_page'] ?? 15)->withQueryString()
        );
    }

    public function store(PromotionRequest $request): PromotionResource
    {
        return new PromotionResource(Promotion::create($request->promotionAttributes()));
    }

    public function show(Promotion $promotion): PromotionResource
    {
        return new PromotionResource($promotion);
    }

    public function update(PromotionRequest $request, Promotion $promotion): PromotionResource
    {
        $promotion->update($request->promotionAttributes());

        return new PromotionResource($promotion);
    }

    /**
     * Past orders keep their promotion_code snapshot; carts using the code simply lose it.
     */
    public function destroy(Promotion $promotion): Response
    {
        $promotion->delete();

        return response()->noContent();
    }
}
