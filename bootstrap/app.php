<?php

use App\Traits\ApiResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
// Removed: use Throwable; (not needed, as it's in the global namespace)

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        apiPrefix: '/api'
    )
    ->withMiddleware(function (Middleware $middleware) {


    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Create an instance of a class using the ApiResponse trait
        $responder = new class {
            use ApiResponse;
        };

        // Handle Validation Exceptions
        $exceptions->render(function (ValidationException $e, Request $request) use ($responder) {
            return $responder->errorResponse('Validation failed', 422, $e->errors());
        });

        // Handle Authentication Exceptions
        $exceptions->render(function (AuthenticationException $e, Request $request) use ($responder) {
            $message = 'Unauthorized: ';
            if (!$request->bearerToken()) {
                $message .= 'No token provided.';
            } elseif (!Auth::guard('sanctum')->check()) {
                $message .= 'Invalid or expired token.';
            } else {
                $message .= 'Authentication failed.';
            }
            return $responder->errorResponse($message, 401);
        });

        // Handle Model Not Found Exceptions
        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($responder) {
            return $responder->errorResponse('Resource not found', 404);
        });

        // Rate limit exceeded
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($responder) {
            return $responder->errorResponse('Too many requests. Please try again later.', 429);
        });

        // Handle Route Not Found Exceptions
        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($responder) {
            return $responder->errorResponse('Route not found', 404);
        });

        // Handle All Other Exceptions (including 500 errors)
        $exceptions->render(function (Throwable $e, Request $request) use ($responder) {
            $message = app()->environment('production')
                ? 'The server encountered an unexpected error. Please try again later.'
                : 'Server error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' at line ' . $e->getLine();
            return $responder->errorResponse($message, 500);
        });
    })->create();