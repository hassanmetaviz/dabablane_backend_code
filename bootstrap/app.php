<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Http\Request;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*')) {
                return route('api.login');
            } else {
                return route('/');
            }
        });
        $middleware->group('api', [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            // Rate limiting disabled temporarily to fix 500 errors
            // \Illuminate\Routing\Middleware\ThrottleRequests::class . ':api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\WrapApiResponse::class,
        ]);
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'verify_frontend_request' => \App\Http\Middleware\VerifyFrontendRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            // Only standardize API responses
            $isApi = $request->is('api/*') || $request->expectsJson();

            if (!$isApi) {
                return null;
            }

            // Validation errors
            if ($e instanceof ValidationException) {
                return response()->json([
                    'status' => false,
                    'code' => 422,
                    'message' => 'Validation error',
                    'errors' => $e->errors(),
                ], 422);
            }

            // Handle authentication exceptions
            if ($e instanceof AuthenticationException) {
                return response()->json(
                    [
                        'status' => false,
                        'code' => 401,
                        'message' => $e->getMessage() ?: 'Unauthenticated',
                    ],
                    401,
                );
            }

            // Handle authorization exceptions
            if ($e instanceof AuthorizationException) {
                return response()->json(
                    [
                        'status' => false,
                        'code' => 403,
                        'message' => $e->getMessage() ?: 'Unauthorized: You do not have permission to perform this action',
                    ],
                    403,
                );
            }

            // 404
            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                $message = config('app.debug') ? $e->getMessage() : 'Not found';
                return response()->json([
                    'status' => false,
                    'code' => 404,
                    'message' => $message,
                ], 404);
            }

            // Generic API error
            $statusCode = $e instanceof HttpException ? $e->getStatusCode() : 500;
            $message = config('app.debug') ? $e->getMessage() : 'Internal server error.';

            return response()->json([
                'status' => false,
                'code' => $statusCode,
                'message' => $message,
            ], $statusCode);
        });
    })->create();
