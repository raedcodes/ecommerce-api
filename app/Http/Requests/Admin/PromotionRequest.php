<?php

namespace App\Http\Requests\Admin;

use App\Enums\PromotionType;
use App\Models\Promotion;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create (POST: code, type and value required) or partially update (PUT/PATCH) a promotion.
 *
 * `value` is a whole percentage (1–100) for percentage codes and a dollar amount for fixed codes.
 * On update, cross-field checks use the stored value of any field that is not being changed.
 */
class PromotionRequest extends FormRequest
{
    /**
     * Admin access is enforced by the route's `can:admin` middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalise the code first so uniqueness is checked case-insensitively.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => Promotion::normalizeCode($this->input('code'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $presence = $this->isMethod('POST') ? 'required' : 'sometimes';
        $amount = ['numeric', 'decimal:0,2', 'max:'.ProductRequest::MAX_PRICE];
        $limit = ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.ProductRequest::MAX_STOCK];

        return [
            'code' => [
                $presence,
                'string',
                'max:50',
                'regex:/^[A-Z0-9_\-]+$/',
                Rule::unique('promotions', 'code')->ignore($this->promotion()?->id),
            ],
            'type' => [$presence, Rule::enum(PromotionType::class)],
            'value' => [$presence, ...$amount, 'min:0.01'],
            'min_cart_amount' => ['sometimes', 'nullable', ...$amount, 'min:0'],
            'max_discount_amount' => ['sometimes', 'nullable', ...$amount, 'min:0.01'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
            'usage_limit' => $limit,
            'per_customer_limit' => $limit,
            'is_active' => ['sometimes', 'boolean'],
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
            'code.regex' => 'The code may only contain letters, numbers, dashes and underscores.',
        ];
    }

    /**
     * Checks that depend on the effective (submitted or stored) type, value and dates.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $errors = $validator->errors();

                if (! $errors->hasAny(['type', 'value'])) {
                    $type = $this->effectiveType();
                    $typeChanged = $this->promotion() !== null && $this->has('type') && $type !== $this->promotion()->type;

                    if ($typeChanged && ! $this->has('value')) {
                        $errors->add('value', 'The value field is required when changing the type.');
                    } elseif ($type === PromotionType::Percentage && $this->has('value') && ! $this->isWholePercentage($this->input('value'))) {
                        $errors->add('value', 'A percentage value must be a whole number between 1 and 100.');
                    }
                }

                if (! $errors->hasAny(['starts_at', 'ends_at'])) {
                    $startsAt = $this->has('starts_at') ? $this->date('starts_at') : $this->promotion()?->starts_at;
                    $endsAt = $this->has('ends_at') ? $this->date('ends_at') : $this->promotion()?->ends_at;

                    if ($startsAt instanceof Carbon && $endsAt instanceof Carbon && $endsAt->lte($startsAt)) {
                        $errors->add('ends_at', 'The end date must be after the start date.');
                    }
                }
            },
        ];
    }

    /**
     * The validated attributes ready to store (amounts converted to cents).
     *
     * @return array<string, mixed>
     */
    public function promotionAttributes(): array
    {
        $attributes = $this->validated();

        if (array_key_exists('value', $attributes)) {
            $attributes['value'] = $this->effectiveType() === PromotionType::Percentage
                ? (int) $attributes['value']
                : Money::toCents($attributes['value']);
        }

        foreach (['min_cart_amount', 'max_discount_amount'] as $field) {
            if (isset($attributes[$field])) {
                $attributes[$field] = Money::toCents($attributes[$field]);
            }
        }

        return $attributes;
    }

    private function promotion(): ?Promotion
    {
        $promotion = $this->route('promotion');

        return $promotion instanceof Promotion ? $promotion : null;
    }

    private function effectiveType(): ?PromotionType
    {
        return $this->has('type')
            ? PromotionType::tryFrom((string) $this->input('type'))
            : $this->promotion()?->type;
    }

    private function isWholePercentage(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]) !== false;
    }
}
