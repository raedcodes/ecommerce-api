<?php

namespace App\OpenApi\Paths;

use OpenApi\Attributes as OA;

/**
 * OpenAPI operations for customer authentication.
 *
 * Documentation only; the handlers are App\Http\Controllers\Api\AuthController.
 * tests/Feature/ApiDocumentationTest.php fails if a route is missing here or a documented route no longer exists.
 */
final class AuthPaths
{
    #[OA\Post(
        path: '/api/auth/register',
        operationId: 'register',
        description: 'Rate limited to 5 requests per minute per IP.',
        summary: 'Register a customer and get a token',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Jane Customer'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jane@example.com'),
                    new OA\Property(
                        property: 'password',
                        type: 'string',
                        format: 'password',
                        minLength: 8,
                        example: 'secret-password',
                    ),
                    new OA\Property(
                        property: 'password_confirmation',
                        type: 'string',
                        format: 'password',
                        example: 'secret-password',
                    ),
                ],
            ),
        ),
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Registered.',
                content: new OA\JsonContent(ref: '#/components/schemas/AuthToken'),
            ),
            new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function register(): void {}

    #[OA\Post(
        path: '/api/auth/login',
        operationId: 'login',
        description: 'Rate limited to 5 attempts per minute per email and IP. Demo accounts (local seed data): customer@example.com and admin@example.com, password "password".',
        summary: 'Log in and get a token',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'customer@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password'),
                ],
            ),
        ),
        tags: ['Auth'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logged in.',
                content: new OA\JsonContent(ref: '#/components/schemas/AuthToken'),
            ),
            new OA\Response(
                response: 422,
                description: 'Invalid input, or the credentials do not match (same message for an unknown email and a wrong password).',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ValidationError',
                    example: [
                        'message' => 'These credentials do not match our records.',
                        'code' => 'validation_failed',
                        'errors' => ['email' => ['These credentials do not match our records.']],
                    ],
                ),
            ),
            new OA\Response(ref: '#/components/responses/TooManyRequests', response: 429),
        ],
    )]
    public function login(): void {}

    #[OA\Post(
        path: '/api/auth/logout',
        operationId: 'logout',
        description: 'Revoke only the token used for this request; other devices stay signed in.',
        summary: 'Revoke the current token',
        security: [['sanctum' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 204, description: 'Token revoked; other tokens stay valid.'),
            new OA\Response(ref: '#/components/responses/Unauthenticated', response: 401),
        ],
    )]
    public function logout(): void {}
}
