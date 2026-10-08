<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Services\ProductCatalog;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'sku', 'description', 'price', 'stock_quantity', 'status'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * Columns a client may sort the catalog by (each also allowed with a "-" prefix for descending).
     *
     * @var list<string>
     */
    public const SORTABLE_COLUMNS = ['price', 'name', 'created_at'];

    protected static function booted(): void
    {
        static::saved(fn () => ProductCatalog::invalidate());
        static::deleted(fn () => ProductCatalog::invalidate());
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'stock_quantity' => 'integer',
            'status' => ProductStatus::class,
        ];
    }

    /**
     * Quantity a customer can buy right now; inactive products count as unavailable.
     */
    public function availableQuantity(): int
    {
        return $this->status === ProductStatus::Active ? $this->stock_quantity : 0;
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', ProductStatus::Active);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inStock(Builder $query, bool $inStock = true): void
    {
        $inStock
            ? $query->where('stock_quantity', '>', 0)
            : $query->where('stock_quantity', 0);
    }
}
