<?php

use App\Http\Middleware\EnsureCorrelationId;
use App\Http\Middleware\SecureHeaders;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;
use Sentry\State\Scope;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(EnsureCorrelationId::class);
        $middleware->append(SecureHeaders::class);

        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '*'),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX
        );

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        $exceptions->reportable(function (\Throwable $throwable): void {
            if (! app()->bound('request')) {
                return;
            }

            $request = request();
            if (! $request instanceof Request) {
                return;
            }

            $correlationId = trim((string) $request->attributes->get('correlation_id', ''));
            if ($correlationId === '') {
                $correlationId = trim((string) $request->headers->get('X-Correlation-Id', ''));
            }

            \Sentry\configureScope(function (Scope $scope) use ($request, $correlationId): void {
                if ($correlationId !== '') {
                    $scope->setTag('correlation_id', $correlationId);
                }

                $route = $request->route();
                $scope->setContext('http_request', array_filter([
                    'method' => $request->method(),
                    'path' => '/' . ltrim($request->path(), '/'),
                    'route_name' => is_object($route) ? $route->getName() : null,
                    'request_id' => trim((string) $request->headers->get('X-Request-Id', '')),
                ], static fn (mixed $value): bool => $value !== null && $value !== ''));

                $user = $request->user();
                if ($user !== null) {
                    $scope->setUser(['id' => (string) $user->getAuthIdentifier()]);
                }
            });
        });
    })->create();
