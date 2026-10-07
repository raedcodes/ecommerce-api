<?php

namespace App\Models;

use App\Enums\PromotionType;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code',
    'type',
    'value',
    'min_cart_amount',
    'max_discount_amount',
    'starts_at',
    'ends_at',
    'usage_limit',
    'per_customer_limit',
    'is_active',
])]
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PromotionType::class,
            'value' => 'integer',
            'min_cart_amount' => 'integer',
            'max_discount_amount' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit' => 'integer',
            'per_customer_limit' => 'integer',
            'times_used' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Codes are stored trimmed and upper-cased so lookups are case-insensitive.
     *
     * @return Attribute<string, string>
     */
    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => strtoupper(trim($value)),
        );
    }
}
