<?php

use App\Models\Order;
use App\Models\User;
use App\Policies\OrderPolicy;

test('customers may view only their own orders', function (bool $isOwner, bool $allowed) {
    $owner = User::factory()->create();
    $order = Order::factory()->for($owner)->create();
    $actor = $isOwner ? $owner : User::factory()->create();

    expect((new OrderPolicy)->view($actor, $order)->allowed())->toBe($allowed);
})->with([
    'owner' => [true, true],
    'another customer' => [false, false],
]);
