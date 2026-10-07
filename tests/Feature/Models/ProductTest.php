<?php

use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('the database rejects a stock decrement below zero', function () {
    $product = Product::factory()->create(['stock_quantity' => 2]);

    expect(fn () => Product::whereKey($product->id)->decrement('stock_quantity', 3))
        ->toThrow(QueryException::class);

    expect($product->fresh()->stock_quantity)->toBe(2);
});

test('the database rejects a status outside the allowed enum values', function () {
    $product = Product::factory()->create();

    expect(fn () => DB::table('products')->where('id', $product->id)->update(['status' => 'archived']))
        ->toThrow(QueryException::class);
});
