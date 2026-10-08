<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create (POST: fields required) or partially update (PUT/PATCH: fields optional) a product.
 * Prices are entered in dollars and stored in cents.
 */
class ProductRequest extends FormRequest
{
    /**
     * Upper bounds of the unsigned INT columns, in the units the API accepts.
     */
    public const MAX_PRICE = '42949672.95';

    public const MAX_STOCK = 4_294_967_295;

    /**
     * Admin access is enforced by the route's `can:admin` middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';
        $product = $this->route('product');

        return [
            'name' => [$presence, 'string', 'max:255'],
            'sku' => [
                $presence,
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9_.\-]+$/',
                Rule::unique('products', 'sku')->ignore($product instanceof Product ? $product->id : null),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'price' => [$presence, 'numeric', 'decimal:0,2', 'min:0.01', 'max:'.self::MAX_PRICE],
            'stock_quantity' => [$presence, 'integer', 'min:0', 'max:'.self::MAX_STOCK],
            'status' => ['sometimes', Rule::enum(ProductStatus::class)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.regex' => 'The SKU may only contain letters, numbers, dots, dashes and underscores.',
        ];
    }

    /**
     * The validated attributes ready to store (price converted to cents).
     *
     * @return array<string, mixed>
     */
    public function productAttributes(): array
    {
        $attributes = $this->validated();

        if (array_key_exists('price', $attributes)) {
            $attributes['price'] = Money::toCents($attributes['price']);
        }

        return $attributes;
    }
}
