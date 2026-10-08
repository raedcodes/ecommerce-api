<?php

use App\Models\Product;
use Illuminate\Support\Facades\DB;

describe('index', function () {
    test('it returns a paginated list of active products without authentication', function () {
        Product::factory()->count(2)->create();
        Product::factory()->inactive()->create();

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'sku', 'description', 'price', 'stock_quantity', 'in_stock', 'status']],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    });

    test('it paginates with the requested page size and keeps filters in the links', function () {
        Product::factory()->count(12)->create();

        $response = $this->getJson('/api/products?per_page=5&page=2&sort=price&filter[in_stock]=true')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 12);

        expect(urldecode($response->json('links.next')))
            ->toContain('page=3')
            ->toContain('per_page=5')
            ->toContain('sort=price')
            ->toContain('filter[in_stock]=true');
    });

    test('it searches names case-insensitively', function () {
        Product::factory()->create(['name' => 'Walnut Desk Lamp']);
        Product::factory()->create(['name' => 'Oak Bookshelf']);

        $this->getJson('/api/products?filter[name]=desk')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Walnut Desk Lamp');
    });

    test('it matches special characters in the search term literally', function (string $term, string $expectedName) {
        Product::factory()->create(['name' => '100% Cotton Tee']);
        Product::factory()->create(['name' => '1000 Thread Sheets']);
        Product::factory()->create(['name' => 'Snake_Case Mug']);
        Product::factory()->create(['name' => 'SnakeXCase Mug']);
        Product::factory()->create(['name' => 'Lamp, Desk Edition']);
        Product::factory()->create(['name' => 'Floor Lamp']);

        $this->getJson('/api/products?filter[name]='.urlencode($term))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', $expectedName);
    })->with([
        'percent sign' => ['100%', '100% Cotton Tee'],
        'underscore' => ['Snake_Case', 'Snake_Case Mug'],
        'comma is not split into an OR search' => ['lamp, desk', 'Lamp, Desk Edition'],
    ]);

    test('it filters by an inclusive price range given in dollars', function () {
        Product::factory()->create(['name' => 'Below', 'price' => 999]);
        Product::factory()->create(['name' => 'At Minimum', 'price' => 2500]);
        Product::factory()->create(['name' => 'At Maximum', 'price' => 5050]);
        Product::factory()->create(['name' => 'Above', 'price' => 5051]);

        $this->getJson('/api/products?filter[min_price]=25&filter[max_price]=50.50&sort=price')
            ->assertOk()
            ->assertJsonPath('data.*.name', ['At Minimum', 'At Maximum']);
    });

    test('it filters by availability', function (string $value, string $expectedName) {
        Product::factory()->create(['name' => 'Stocked Lamp', 'stock_quantity' => 3]);
        Product::factory()->outOfStock()->create(['name' => 'Sold Out Lamp']);

        $this->getJson("/api/products?filter[in_stock]={$value}")
            ->assertOk()
            ->assertJsonPath('data.*.name', [$expectedName]);
    })->with([
        'true' => ['true', 'Stocked Lamp'],
        '1' => ['1', 'Stocked Lamp'],
        'false' => ['false', 'Sold Out Lamp'],
        '0' => ['0', 'Sold Out Lamp'],
    ]);

    test('it sorts by the requested column and direction', function (?string $sort, array $expectedOrder) {
        Product::factory()->create(['name' => 'Banana', 'price' => 2000, 'created_at' => now()->subDays(2)]);
        Product::factory()->create(['name' => 'Apple', 'price' => 3000, 'created_at' => now()->subDay()]);
        Product::factory()->create(['name' => 'Cherry', 'price' => 1000, 'created_at' => now()]);

        $this->getJson('/api/products'.($sort ? "?sort={$sort}" : ''))
            ->assertOk()
            ->assertJsonPath('data.*.name', $expectedOrder);
    })->with([
        'default is newest first' => [null, ['Cherry', 'Apple', 'Banana']],
        'price ascending' => ['price', ['Cherry', 'Banana', 'Apple']],
        'price descending' => ['-price', ['Apple', 'Banana', 'Cherry']],
        'name ascending' => ['name', ['Apple', 'Banana', 'Cherry']],
        'name descending' => ['-name', ['Cherry', 'Banana', 'Apple']],
        'oldest first' => ['created_at', ['Banana', 'Apple', 'Cherry']],
    ]);

    test('it returns 422 for invalid query parameters', function (array $query, string $field) {
        $this->getJson('/api/products?'.http_build_query($query))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors($field);
    })->with([
        'unknown sort column' => [['sort' => 'password'], 'sort'],
        'SQL in sort' => [['sort' => 'price;DROP TABLE products'], 'sort'],
        'unknown filter' => [['filter' => ['password' => 'x']], 'filter'],
        'filter that is not an array' => [['filter' => 'abc'], 'filter'],
        'non-numeric price' => [['filter' => ['min_price' => 'abc']], 'filter.min_price'],
        'negative price' => [['filter' => ['min_price' => '-1']], 'filter.min_price'],
        'more than two decimals' => [['filter' => ['min_price' => '1.999']], 'filter.min_price'],
        'max below min' => [['filter' => ['min_price' => '50', 'max_price' => '10']], 'filter.max_price'],
        'page size above 100' => [['per_page' => '101'], 'per_page'],
        'unknown availability value' => [['filter' => ['in_stock' => 'maybe']], 'filter.in_stock'],
    ]);

    test('it lists the supported filters when an unknown filter is sent', function () {
        $this->getJson('/api/products?filter[password]=x')
            ->assertJsonValidationErrors([
                'filter' => 'The filter field only supports: name, min_price, max_price, in_stock.',
            ]);
    });

    test('it returns the allowed values when the sort is invalid', function () {
        $this->getJson('/api/products?sort=password')
            ->assertJsonValidationErrors([
                'sort' => 'The sort field must be one of: price, -price, name, -name, created_at, -created_at.',
            ]);
    });

    test('it serves a cached listing until a product is changed', function () {
        $product = Product::factory()->create(['name' => 'Original Name']);
        $this->getJson('/api/products')->assertJsonPath('data.0.name', 'Original Name');

        // A write that bypasses the model is invisible, which proves the response is cached.
        DB::table('products')->where('id', $product->id)->update(['name' => 'Changed Behind The Cache']);
        $this->getJson('/api/products')->assertJsonPath('data.0.name', 'Original Name');

        $product->update(['name' => 'Updated Through The Model']);
        $this->getJson('/api/products')->assertJsonPath('data.0.name', 'Updated Through The Model');
    });

    test('it returns 429 after 60 requests in a minute', function () {
        foreach (range(1, 60) as $request) {
            $this->getJson('/api/products')->assertOk();
        }

        $this->getJson('/api/products')
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'too_many_requests');
    });
});

describe('show', function () {
    test('it returns the product details', function () {
        $product = Product::factory()->create(['name' => 'Desk Lamp', 'sku' => 'LMP-00001', 'price' => 14999, 'stock_quantity' => 7]);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', 'Desk Lamp')
            ->assertJsonPath('data.sku', 'LMP-00001')
            ->assertJsonPath('data.price', '149.99')
            ->assertJsonPath('data.stock_quantity', 7)
            ->assertJsonPath('data.in_stock', true);
    });

    test('it returns 404 product_not_found for a missing or inactive product', function (bool $inactive) {
        $id = $inactive ? Product::factory()->inactive()->create()->id : 999999;

        $this->getJson("/api/products/{$id}")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Product not found.', 'code' => 'product_not_found']);
    })->with([
        'missing' => [false],
        'inactive' => [true],
    ]);

    test('it returns 404 for an id that is not a valid product key', function (string $id) {
        $this->getJson("/api/products/{$id}")->assertNotFound();
    })->with([
        'non-numeric' => ['abc'],
        'larger than a 64-bit integer' => ['99999999999999999999999'],
    ]);
});
