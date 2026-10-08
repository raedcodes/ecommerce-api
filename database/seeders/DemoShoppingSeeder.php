<?php

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Gives the first customer a cart ready for checkout (eligible for SUMMER20) and the second
 * customer past orders in every status, so cancellation and its 409 can be tried straight away.
 */
class DemoShoppingSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCart(User::where('email', UserSeeder::CUSTOMER_EMAIL)->sole());
        $this->seedOrderHistory(User::where('email', UserSeeder::SECOND_CUSTOMER_EMAIL)->sole());
    }

    /**
     * Mug × 2 + keyboard = $154.00, above SUMMER20's $100 minimum.
     */
    private function seedCart(User $customer): void
    {
        $cart = $customer->cart()->firstOrCreate();

        if ($cart->items()->exists()) {
            return;
        }

        $cart->items()->createMany([
            ['product_id' => Product::where('sku', 'MUG-001')->value('id'), 'quantity' => 2],
            ['product_id' => Product::where('sku', 'KEYB-001')->value('id'), 'quantity' => 1],
        ]);
    }

    private function seedOrderHistory(User $customer): void
    {
        if ($customer->orders()->exists()) {
            return;
        }

        $headphones = Product::where('sku', 'HEAD-001')->sole();
        $mug = Product::where('sku', 'MUG-001')->sole();

        $this->order($customer, OrderStatus::Pending, [[$mug, 1]]);
        $this->order($customer, OrderStatus::Processing, [[$mug, 3]]);
        $this->order($customer, OrderStatus::Shipped, [[$headphones, 1]]);
        $this->order($customer, OrderStatus::Delivered, [[$headphones, 1], [$mug, 2]]);
        $this->order($customer, OrderStatus::Cancelled, [[$mug, 1]]);
    }

    /**
     * An order snapshotting the products' current name, SKU and price, as checkout would.
     *
     * @param  list<array{0: Product, 1: int}>  $lines
     */
    private function order(User $customer, OrderStatus $status, array $lines): void
    {
        $items = array_map(fn (array $line): array => [
            'product_id' => $line[0]->id,
            'product_name' => $line[0]->name,
            'product_sku' => $line[0]->sku,
            'unit_price' => $line[0]->price,
            'quantity' => $line[1],
            'line_total' => $line[0]->price * $line[1],
        ], $lines);

        $subtotal = array_sum(array_column($items, 'line_total'));

        $order = $customer->orders()->create([
            'status' => $status,
            'subtotal' => $subtotal,
            'discount_amount' => 0,
            'total' => $subtotal,
            'cancelled_at' => $status === OrderStatus::Cancelled ? now() : null,
        ]);

        $order->items()->createMany($items);
    }
}
