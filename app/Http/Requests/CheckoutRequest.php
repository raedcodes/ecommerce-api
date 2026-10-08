<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Checkout takes no body: the order is built from the server-side cart. The only input is the
 * optional `Idempotency-Key` header, which is validated here as `idempotency_key`.
 */
class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The header always wins, so a body field cannot pose as the idempotency key.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\-:.]+$/'],
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
            'idempotency_key.max' => 'The Idempotency-Key header may not be longer than 100 characters.',
            'idempotency_key.regex' => 'The Idempotency-Key header may only contain letters, numbers, dashes, underscores, colons and dots.',
        ];
    }
}
