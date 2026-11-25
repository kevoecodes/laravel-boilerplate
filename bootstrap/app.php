<?php

use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prependToGroup('api', ForceJsonResponse::class);
        // API only: never redirect unauthenticated users to a login page.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $isApi = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $isApi($request));

        // Every API error uses the same shape: { "message": string, "errors"?: object }
        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], $e->status);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json(['message' => $e->getMessage() ?: 'This action is unauthorized.'], 403);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                $message = $e->getPrevious() instanceof ModelNotFoundException
                    ? 'Resource not found.'
                    : 'Endpoint not found.';

                return response()->json(['message' => $message], 404);
            }
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json(
                    ['message' => $e->getMessage() ?: 'HTTP error.'],
                    $e->getStatusCode(),
                    $e->getHeaders(),
                );
            }
        });
    })->create();
