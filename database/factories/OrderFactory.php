<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(1000, 100000);

        return [
            'user_id' => User::factory(),
            'status' => OrderStatus::Pending,
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'total' => $subtotal,
            'promotion_id' => null,
            'promotion_code' => null,
            'idempotency_key' => null,
            'cancelled_at' => null,
        ];
    }

    /**
     * Indicate that the order is being prepared.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Processing,
        ]);
    }

    /**
     * Indicate that the order has been shipped.
     */
    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Shipped,
        ]);
    }

    /**
     * Indicate that the order has been delivered.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Delivered,
        ]);
    }

    /**
     * Indicate that the order has been cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
