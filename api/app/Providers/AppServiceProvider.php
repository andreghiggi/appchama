<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Notificações push da corrida (Expo).
        Event::listen(\App\Events\RideOfferReceived::class, [\App\Listeners\EnviarPushDaCorrida::class, 'handleOffer']);
        Event::listen(\App\Events\RideStatusChanged::class, [\App\Listeners\EnviarPushDaCorrida::class, 'handleStatus']);

        // Codigo de acesso: limita pedidos e tentativas por telefone e por IP.
        RateLimiter::for('otp-send', fn (Request $r) => [
            Limit::perMinutes(10, 5)->by('tel:'.preg_replace('/\D/', '', (string) $r->input('phone'))),
            Limit::perMinutes(10, 20)->by('ip:'.$r->ip()),
        ]);
        RateLimiter::for('otp-verify', fn (Request $r) => [
            Limit::perMinutes(10, 10)->by('tel:'.preg_replace('/\D/', '', (string) $r->input('phone'))),
            Limit::perMinutes(10, 30)->by('ip:'.$r->ip()),
        ]);
    }
}
