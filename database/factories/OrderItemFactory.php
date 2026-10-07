<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'product_name' => ucwords(fake()->words(3, true)),
            'product_sku' => strtoupper(fake()->bothify('???-#####')),
            'unit_price' => fake()->numberBetween(500, 50000),
            'quantity' => fake()->numberBetween(1, 3),
            'line_total' => fn (array $attributes) => $attributes['unit_price'] * $attributes['quantity'],
        ];
    }
}
