<?php

use App\Exceptions\ApiException;
use App\Models\Product;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;

function throwApiException(): never
{
    throw new class('Only 3 units are available.', 'insufficient_stock', 422, ['available' => 3]) extends ApiException {};
}

test('a missing bound model returns 404 with a resource code and no class name', function () {
    Route::middleware('api')->get('api/_test/products/{product}', fn (Product $product) => $product);

    $this->get('api/_test/products/999')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Product not found.', 'code' => 'product_not_found']);
});

test('an unknown api route returns a JSON 404 without an Accept header', function () {
    $this->get('api/does-not-exist')
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');
});

test('an unauthenticated request returns 401 JSON without an Accept header', function () {
    Route::middleware(['api', 'auth:sanctum'])->get('api/_test/protected', fn () => 'ok');

    $this->get('api/_test/protected')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Unauthenticated.', 'code' => 'unauthenticated']);
});

test('a denied authorization returns 403 with a forbidden code', function () {
    Route::middleware('api')->get('api/_test/forbidden', fn () => throw new AuthorizationException);

    $this->get('api/_test/forbidden')
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');
});

test('a validation failure returns 422 with a code and the field errors', function () {
    Route::middleware('api')->post('api/_test/validate', fn (Request $request) => $request->validate(['quantity' => 'required|integer']));

    $this->postJson('api/_test/validate', [])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('quantity');
});

test('a domain exception renders its status, code and details', function () {
    Route::middleware('api')->get('api/_test/domain', fn () => throwApiException());

    $this->get('api/_test/domain')
        ->assertUnprocessable()
        ->assertExactJson([
            'message' => 'Only 3 units are available.',
            'code' => 'insufficient_stock',
            'details' => ['available' => 3],
        ]);
});

test('a domain exception is not reported to the logs', function () {
    Exceptions::fake();
    Route::middleware('api')->get('api/_test/domain', fn () => throwApiException());

    $this->get('api/_test/domain');

    Exceptions::assertNothingReported();
});
