<?php

namespace Database\Seeders;

use App\Enums\PromotionType;
use App\Models\Promotion;
use Illuminate\Database\Seeder;

/**
 * One promotion per eligibility rule, so every coupon error can be tried. Matched by code.
 */
class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        // The example from the requirements.
        $this->promotion('SUMMER20', [
            'type' => PromotionType::Percentage,
            'value' => 20,
            'min_cart_amount' => 10000,
            'max_discount_amount' => 5000,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->addMonths(2),
            'usage_limit' => 1000,
            'per_customer_limit' => 1,
        ]);

        $this->promotion('WELCOME10', [
            'type' => PromotionType::Fixed,
            'value' => 1000,
            'per_customer_limit' => 1,
        ]);

        $this->promotion('EXPIRED5', [
            'type' => PromotionType::Percentage,
            'value' => 5,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDay(),
        ]);

        $this->promotion('FUTURE15', [
            'type' => PromotionType::Percentage,
            'value' => 15,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addMonth(),
        ]);

        $this->promotion('PAUSED25', [
            'type' => PromotionType::Percentage,
            'value' => 25,
            'is_active' => false,
        ]);

        // Already used up: times_used is not mass-assignable, so it is force-filled.
        $this->promotion('LIMITED1', [
            'type' => PromotionType::Fixed,
            'value' => 500,
            'usage_limit' => 1,
        ])->forceFill(['times_used' => 1])->save();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function promotion(string $code, array $attributes): Promotion
    {
        return Promotion::updateOrCreate(['code' => $code], [
            'min_cart_amount' => null,
            'max_discount_amount' => null,
            'starts_at' => null,
            'ends_at' => null,
            'usage_limit' => null,
            'per_customer_limit' => null,
            'is_active' => true,
            ...$attributes,
        ]);
    }
}
