<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('only administrators pass the admin gate', function (bool $isAdmin) {
    $user = $isAdmin ? User::factory()->admin()->create() : User::factory()->create();

    expect(Gate::forUser($user)->allows('admin'))->toBe($isAdmin);
})->with([
    'administrator' => [true],
    'customer' => [false],
]);
