<?php

use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/**
 * @return array<string, mixed>
 */
function committedOpenApi(): array
{
    return json_decode(File::get(storage_path('api-docs/api-docs.json')), true, flags: JSON_THROW_ON_ERROR);
}

test('the committed OpenAPI document matches the code', function () {
    $directory = storage_path('framework/testing/api-docs-'.uniqid());
    config(['l5-swagger.defaults.paths.docs' => $directory]);

    try {
        Artisan::call('l5-swagger:generate');
        $generated = json_decode(File::get("{$directory}/api-docs.json"), true, flags: JSON_THROW_ON_ERROR);
    } finally {
        File::deleteDirectory($directory);
    }

    expect($generated)->toBe(committedOpenApi(), 'Run `php artisan l5-swagger:generate` and commit storage/api-docs/api-docs.json.');
});

test('every API route is documented and every documented operation exists', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => str_starts_with($route->uri(), 'api/'))
        ->reject(fn (RoutingRoute $route) => in_array($route->uri(), ['api/documentation', 'api/oauth2-callback'], true))
        ->flatMap(function (RoutingRoute $route) {
            $methods = array_diff($route->methods(), ['HEAD']);

            // PUT is documented as an alias of PATCH on resource updates.
            if (in_array('PATCH', $methods, true)) {
                $methods = array_diff($methods, ['PUT']);
            }

            return array_map(fn (string $method) => $method.' /'.$route->uri(), $methods);
        })
        ->sort()
        ->values()
        ->all();

    $documented = collect(committedOpenApi()['paths'])
        ->flatMap(fn (array $operations, string $path) => array_map(
            fn (string $method) => strtoupper($method).' '.$path,
            array_keys(array_intersect_key($operations, array_flip(['get', 'post', 'put', 'patch', 'delete']))),
        ))
        ->sort()
        ->values()
        ->all();

    expect($documented)->toBe($routes);
});

test('the Swagger UI and the JSON document are served', function () {
    $this->get('/api/documentation')->assertOk()->assertSee('swagger-ui', false);

    $this->get('/docs')->assertOk()->assertJsonPath('info.title', 'E-Commerce Order & Promotion API');
});
