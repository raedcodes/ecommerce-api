<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PromotionType;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Mirrors the column defaults so freshly created promotions serialize correctly.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'times_used' => 0,
        'is_active' => true,
    ];

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
     */
    public static function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    public static function findByCode(string $code): ?self
    {
        return self::query()->where('code', self::normalizeCode($code))->first();
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Times the customer has used this code; cancelled orders give the use back.
     */
    public function timesUsedBy(User $customer): int
    {
        return $this->orders()
            ->whereBelongsTo($customer)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->count();
    }

    /**
     * @return Attribute<string, string>
     */
    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeCode($value),
        );
    }
}
