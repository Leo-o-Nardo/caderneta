<?php

use App\Http\Middleware\EnsureCanWrite;
use App\Http\Middleware\ResolveCurrentHousehold;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // A autorização dos canais privados fica junto da API e usa o token do app (Sanctum), não a
    // sessão de navegador que o padrão do Laravel usaria: POST /api/v1/broadcasting/auth.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', [
        'prefix' => 'api/v1',
        'middleware' => ['api', 'auth:sanctum'],
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'household' => ResolveCurrentHousehold::class,
            'household.write' => EnsureCanWrite::class,
        ]);

        // A casa precisa ser conhecida antes de o Laravel buscar o {category} ou o {card} da URL,
        // para a busca já sair filtrada por ela: o registro de outra casa vira 404.
        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveCurrentHousehold::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // O link de liberação vencido (ou copiado pela metade): a página da Caderneta, e não o 403 do Laravel.
        $exceptions->render(function (InvalidSignatureException $exception, Request $request) {
            if ($request->is('acesso/*')) {
                return response()->view('access.expired', [], 403);
            }
        });
    })->create();
