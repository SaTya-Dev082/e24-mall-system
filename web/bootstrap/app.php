<?php

use App\Http\Middleware\EnsureActiveCustomer;
use App\Http\Middleware\EnsureAdminUser;
use App\Http\Middleware\ValidateStorefrontSession;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [ValidateStorefrontSession::class]);
        $middleware->alias([
            'customer' => EnsureActiveCustomer::class,
            'admin' => EnsureAdminUser::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (QueryException $exception): ?bool {
            if (request()->routeIs('login*', 'register*', 'password.*', 'verification.*') || request()->is('api/v1/login', 'api/v1/register')) {
                Log::error('Authentication storage request failed.', ['exception' => $exception::class, 'code' => $exception->getCode()]);

                return false;
            }

            return null;
        });
        $exceptions->render(function (QueryException $exception, Request $request): ?Response {
            if ($request->is('api/v1/register') && $exception instanceof UniqueConstraintViolationException) {
                $field = match (true) {
                    in_array($exception->index, ['users_email_normalized_unique', 'users_email_unique'], true), in_array('email', $exception->columns, true) => 'email',
                    $exception->index === 'users_phone_unique', in_array('phone', $exception->columns, true) => 'phone',
                    default => null,
                };
                if ($field) {
                    return response()->json(['message' => 'This account information is already registered.', 'errors' => [$field => ['This '.$field.' is already registered.']]], 422);
                }
            }
            if (! $request->routeIs('login*', 'register*', 'password.*', 'verification.*') && ! $request->is('api/v1/login', 'api/v1/register')) {
                return null;
            }
            $message = 'We could not confirm your request. Please try signing in before submitting again if you were creating an account.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 503)->header('Retry-After', '30');
            }
            $errors = (new ViewErrorBag)->put('default', new MessageBag(['service' => $message]));

            return response()->view($request->routeIs('register*') ? 'pages.account.register' : 'pages.account.login', ['errors' => $errors], 503)
                ->header('Cache-Control', 'no-store, private');
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
