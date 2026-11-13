<?php

use Illuminate\Console\Scheduling\Schedule;
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
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [\App\Http\Middleware\ResolveTenantContext::class]);
        $middleware->api(prepend: [\App\Http\Middleware\ResolveTenantContext::class]);
        $middleware->alias([
            'tenant' => \App\Http\Middleware\EnsureTenantScope::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('withdrawals:reconcile')->everyTenMinutes();
        $schedule->command('payments:reconcile-pending')->everyTenMinutes();
        $schedule->command('payments:expire-stale')->daily();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InvalidArgumentException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        });
    })->create();
