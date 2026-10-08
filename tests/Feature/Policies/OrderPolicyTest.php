<?php

use App\Models\Order;
use App\Models\User;
use App\Policies\OrderPolicy;

test('customers may view and cancel only their own orders', function (string $ability, bool $isOwner, bool $allowed) {
    $owner = User::factory()->create();
    $order = Order::factory()->for($owner)->create();
    $actor = $isOwner ? $owner : User::factory()->create();

    expect((new OrderPolicy)->{$ability}($actor, $order)->allowed())->toBe($allowed);
})->with([
    'owner views' => ['view', true, true],
    'another customer views' => ['view', false, false],
    'owner cancels' => ['cancel', true, true],
    'another customer cancels' => ['cancel', false, false],
]);
