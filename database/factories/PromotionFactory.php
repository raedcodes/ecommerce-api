<?php

namespace Database\Factories;

use App\Enums\PromotionType;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('PROMO-####??')),
            'type' => PromotionType::Percentage,
            'value' => 10,
            'min_cart_amount' => null,
            'max_discount_amount' => null,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'usage_limit' => null,
            'per_customer_limit' => null,
            'times_used' => 0,
            'is_active' => true,
        ];
    }

    /**
     * A percentage discount of the given whole percent.
     */
    public function percentage(int $percent): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PromotionType::Percentage,
            'value' => $percent,
        ]);
    }

    /**
     * A fixed discount of the given amount in cents.
     */
    public function fixed(int $amountInCents): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PromotionType::Fixed,
            'value' => $amountInCents,
        ]);
    }

    /**
     * Indicate that the promotion has been switched off.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the promotion's end date has passed.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the promotion has not started yet.
     */
    public function notStarted(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addMonth(),
        ]);
    }

    /**
     * Indicate that the promotion's global usage limit has been reached.
     */
    public function exhausted(): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_limit' => 1,
            'times_used' => 1,
        ]);
    }
}
