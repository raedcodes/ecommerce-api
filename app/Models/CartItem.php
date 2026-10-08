<?php

namespace App\Models;

use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'quantity'])]
class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    /**
     * Upper bound of the unsigned INT quantity column; keeps arithmetic on quantities within PHP int range.
     */
    public const MAX_QUANTITY = 4_294_967_295;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Line total in cents at the product's current price.
     */
    public function lineTotal(): int
    {
        return $this->product->price * $this->quantity;
    }

    /**
     * Whether the requested quantity can currently be bought.
     */
    public function isAvailable(): bool
    {
        return $this->quantity <= $this->product->availableQuantity();
    }
}
