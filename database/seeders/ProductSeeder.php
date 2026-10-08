<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Services\ProductCatalog;
use Illuminate\Database\Seeder;

/**
 * A recognisable catalog for trying the API, plus generated filler for paging and filtering.
 * Products are matched by SKU, so re-running never duplicates them.
 */
class ProductSeeder extends Seeder
{
    public const FILLER_COUNT = 43;

    /**
     * @var list<array{sku: string, name: string, description: string, price: int, stock_quantity: int, status?: ProductStatus}>
     */
    private const CATALOG = [
        ['sku' => 'LAMP-001', 'name' => 'Walnut Desk Lamp', 'description' => 'Warm dimmable LED desk lamp. Only 5 in stock: handy for trying the concurrency scenario.', 'price' => 4999, 'stock_quantity' => 5],
        ['sku' => 'MUG-001', 'name' => 'Ceramic Coffee Mug', 'description' => '350 ml stoneware mug.', 'price' => 1250, 'stock_quantity' => 100],
        ['sku' => 'KEYB-001', 'name' => 'Mechanical Keyboard', 'description' => 'Tenkeyless keyboard with tactile switches.', 'price' => 12900, 'stock_quantity' => 25],
        ['sku' => 'HEAD-001', 'name' => 'Noise-Cancelling Headphones', 'description' => 'Over-ear wireless headphones.', 'price' => 24999, 'stock_quantity' => 10],
        ['sku' => 'CHAIR-001', 'name' => 'Ergonomic Office Chair', 'description' => 'Adjustable lumbar support and armrests.', 'price' => 34900, 'stock_quantity' => 3],
        ['sku' => 'BAG-001', 'name' => 'Canvas Laptop Bag', 'description' => 'Fits 15-inch laptops. Currently out of stock.', 'price' => 5995, 'stock_quantity' => 0],
        ['sku' => 'FAN-001', 'name' => 'Discontinued Desk Fan', 'description' => 'Inactive: hidden from customers, visible to admins.', 'price' => 2999, 'stock_quantity' => 20, 'status' => ProductStatus::Inactive],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $product) {
            Product::firstOrCreate(['sku' => $product['sku']], $product);
        }

        foreach (range(1, self::FILLER_COUNT) as $number) {
            $sku = sprintf('DEMO-%04d', $number);

            Product::firstOrCreate(['sku' => $sku], Product::factory()->raw([
                'sku' => $sku,
                'stock_quantity' => $number % 7 === 0 ? 0 : fake()->numberBetween(5, 100),
                'status' => $number % 10 === 0 ? ProductStatus::Inactive : ProductStatus::Active,
            ]));
        }

        // Model events are disabled while seeding, so refresh cached listings explicitly.
        ProductCatalog::invalidate();
    }
}
