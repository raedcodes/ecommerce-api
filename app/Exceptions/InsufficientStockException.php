<?php

namespace App\Exceptions;

use App\Models\Product;
use Illuminate\Support\Str;

final class InsufficientStockException extends ApiException
{
    /**
     * @param  list<array{product_id: int, name: string, requested: int, available: int}>  $shortages
     */
    public static function forItems(array $shortages): self
    {
        $message = count($shortages) === 1
            ? self::describe($shortages[0])
            : 'Some items exceed the available stock.';

        return new self($message, 'insufficient_stock', 422, ['items' => $shortages]);
    }

    public static function forProduct(Product $product, int $requested): self
    {
        return self::forItems([[
            'product_id' => $product->id,
            'name' => $product->name,
            'requested' => $requested,
            'available' => $product->availableQuantity(),
        ]]);
    }

    /**
     * @param  array{product_id: int, name: string, requested: int, available: int}  $shortage
     */
    private static function describe(array $shortage): string
    {
        if ($shortage['available'] === 0) {
            return "'{$shortage['name']}' is out of stock.";
        }

        return sprintf(
            "Only %d %s of '%s' %s available.",
            $shortage['available'],
            Str::plural('unit', $shortage['available']),
            $shortage['name'],
            $shortage['available'] === 1 ? 'is' : 'are',
        );
    }
}
