<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();
        $middleware->appendToGroup('api', [
            \App\Http\Middleware\ForgetTenantUserFromCentralSession::class,
        ]);
        $middleware->alias([
            'cookie.to.token' => \App\Http\Middleware\CookieToTokenMiddleware::class,
            'tenancy.slug' => \App\Http\Middleware\InitializeTenancyBySlug::class,
            'auth.tenant' => \App\Http\Middleware\AuthenticateTenantApi::class,
        ]);

        // Ordem necessária:
        // 1) tenancy / cookie→token antes de auth:sanctum (token do User no DB do tenant)
        // 2) AuthenticateTenantApi antes de SubstituteBindings ({user} no DB do tenant)
        // InitializeTenancyBySlug só pode aparecer uma vez em prependPriority (última sobrescreve).
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: \App\Http\Middleware\InitializeTenancyBySlug::class,
        );
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: \App\Http\Middleware\CookieToTokenMiddleware::class,
        );
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\AuthenticateTenantApi::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (
            \Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException $e,
            \Illuminate\Http\Request $request
        ) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Tenant não identificado. Envie o header X-Tenant com o slug.',
                ], 400);
            }
        });
    })->create();
