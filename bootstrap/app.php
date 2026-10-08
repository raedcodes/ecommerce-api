<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware(['api', 'auth:sanctum', 'can:admin'])
                ->prefix('api/admin')
                ->name('admin.')
                ->group(__DIR__.'/../routes/admin.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // API-only app: there is no login page, so guests get a 401 instead of a redirect.
        $middleware->redirectGuestsTo(null);

        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['message' => 'Unauthenticated.', 'code' => 'unauthenticated'], 401);
        });

        $exceptions->render(function (ValidationException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'validation_failed',
                'errors' => $e->errors(),
            ], $e->status);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*')) {
                return null;
            }

            $previous = $e->getPrevious();

            // Hide the model class name that Laravel puts in the default message.
            if ($previous instanceof ModelNotFoundException) {
                $resource = Str::snake(class_basename($previous->getModel()));

                return response()->json([
                    'message' => Str::ucfirst(str_replace('_', ' ', $resource)).' not found.',
                    'code' => "{$resource}_not_found",
                ], 404);
            }

            $statusText = Response::$statusTexts[$e->getStatusCode()] ?? 'Error';

            return response()->json([
                'message' => $e->getMessage() ?: $statusText,
                'code' => Str::slug($statusText, '_'),
            ], $e->getStatusCode(), $e->getHeaders());
        });
    })->create();
