<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Gravado pelo JS da sidebar (shadcn) — precisa ser legível sem criptografia.
        $middleware->encryptCookies(except: ['sidebar_state']);

        $middleware->web(append: [
            \App\Http\Middleware\SetCurrentTenantFromSession::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // O tenant precisa estar resolvido antes do route model binding: sem tenant o TenantScope não
        // filtra, e um {document} de outro workspace seria encontrado. Na API, a chave também precede o
        // throttle, que limita por chave.
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\SetCurrentTenantFromSession::class,
        );
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\ThrottleRequests::class,
            prepend: \App\Http\Middleware\AuthenticateApiKey::class,
        );

        $middleware->alias([
            'api.key' => \App\Http\Middleware\AuthenticateApiKey::class,
            'api.scope' => \App\Http\Middleware\RequireApiScope::class,
            'module' => \App\Http\Middleware\EnsureModuleActive::class,
            'module.web' => \App\Http\Middleware\EnsureModuleActiveWeb::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Cartão digitado no formulário de cobrança nunca volta para a sessão como "old input".
        $exceptions->dontFlash(['card_number', 'card_cvv', 'card_expiry_month', 'card_expiry_year', 'card_holder_name']);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
