<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucwords(fake()->words(3, true)),
            'sku' => strtoupper(fake()->unique()->bothify('???-#####')),
            'description' => fake()->sentence(12),
            'price' => fake()->numberBetween(500, 50000),
            'stock_quantity' => fake()->numberBetween(10, 100),
            'status' => ProductStatus::Active,
        ];
    }

    /**
     * Indicate that the product is hidden from customers.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Inactive,
        ]);
    }

    /**
     * Indicate that the product has no stock left.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 0,
        ]);
    }
}
