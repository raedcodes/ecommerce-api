<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the spatie/laravel-query-builder parameters, which the package itself does not
 * value-check: `filter[name|min_price|max_price|in_stock]`, `sort`, `per_page` and `page`.
 */
class ProductIndexRequest extends FormRequest
{
    public const MAX_PER_PAGE = 100;

    public const FILTERS = ['name', 'min_price', 'max_price', 'in_stock'];

    /**
     * Determine if the user is authorized to make this request.
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
        return [
            'filter' => ['nullable', 'array:'.implode(',', self::FILTERS)],
            'filter.name' => ['nullable', 'string', 'max:100'],
            'filter.min_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'filter.max_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', Rule::when($this->filled('filter.min_price'), 'gte:filter.min_price')],
            'filter.in_stock' => ['nullable', Rule::in(['1', '0', 'true', 'false'])],
            'sort' => ['nullable', Rule::in($this->sortOptions())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
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
            'filter.array' => 'The filter field only supports: '.implode(', ', self::FILTERS).'.',
            'filter.in_stock.in' => 'The in stock filter must be true, false, 1 or 0.',
            'sort.in' => 'The sort field must be one of: '.implode(', ', $this->sortOptions()).'.',
        ];
    }

    /**
     * The validated `filter` and `sort` parameters, with empty filters dropped.
     *
     * @return array{filter?: array<string, string>, sort?: string}
     */
    public function catalogQuery(): array
    {
        return array_filter([
            'filter' => array_filter($this->validated('filter') ?? [], fn (mixed $value) => $value !== null),
            'sort' => $this->validated('sort'),
        ]);
    }

    /**
     * @return list<string>
     */
    private function sortOptions(): array
    {
        return collect(Product::SORTABLE_COLUMNS)
            ->flatMap(fn (string $column) => [$column, "-{$column}"])
            ->all();
    }
}
