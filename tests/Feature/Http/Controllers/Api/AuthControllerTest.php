<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

describe('register', function () {
    test('it creates a customer and returns a bearer token', function () {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Jane Customer',
            'email' => 'jane@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'jane@example.com')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email'], 'token']]);

        $user = User::where('email', 'jane@example.com')->sole();
        expect(Hash::check('secret-password', $user->password))->toBeTrue()
            ->and($user->tokens()->count())->toBe(1);
    });

    test('it ignores an attempt to self-assign admin rights', function () {
        $this->postJson('/api/auth/register', [
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'is_admin' => true,
        ])->assertCreated()->assertJsonPath('data.user.is_admin', false);

        expect(User::where('email', 'mallory@example.com')->sole()->is_admin)->toBeFalse();
    });

    test('it returns 422 when required fields are missing', function () {
        $this->postJson('/api/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    });

    test('it returns 422 when the email is already registered', function () {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'Someone',
            'email' => 'taken@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'The email has already been taken.']);
    });

    test('it returns 422 when the password confirmation does not match', function () {
        $this->postJson('/api/auth/register', [
            'name' => 'Someone',
            'email' => 'someone@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'different-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'The password field confirmation does not match.']);
    });
});

describe('login', function () {
    test('it returns a bearer token for valid credentials', function () {
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/auth/login', ['email' => 'jane@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.token_type', 'Bearer');

        expect($user->tokens()->count())->toBe(1);
    });

    test('it returns 422 with the same message for a wrong password or an unknown email', function (string $email, string $password) {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/auth/login', ['email' => $email, 'password' => $password])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'These credentials do not match our records.']);
    })->with([
        'wrong password' => ['jane@example.com', 'wrong-password'],
        'unknown email' => ['nobody@example.com', 'password'],
    ]);

    test('it returns 429 after five failed attempts in a minute', function () {
        User::factory()->create(['email' => 'jane@example.com']);
        $credentials = ['email' => 'jane@example.com', 'password' => 'wrong-password'];

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/auth/login', $credentials)->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', $credentials)
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'too_many_requests')
            ->assertHeader('Retry-After');
    });
});

describe('logout', function () {
    test('it revokes only the token used for the request', function () {
        $user = User::factory()->create();
        $currentToken = $user->createToken('api')->plainTextToken;
        $otherDeviceToken = $user->createToken('api')->accessToken;

        $this->withToken($currentToken)
            ->postJson('/api/auth/logout')
            ->assertNoContent();

        expect(PersonalAccessToken::findToken($currentToken))->toBeNull()
            ->and($otherDeviceToken->fresh())->not->toBeNull();
    });

    test('it returns 401 without a token', function () {
        $this->postJson('/api/auth/logout')->assertUnauthorized();
    });
});
