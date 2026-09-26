<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
        Gate::define('exportar-reportes', fn (User $user) => $user->rol === 'administrador');
        Gate::define('editar-stock-minimo', fn (User $user) => $user->rol === 'administrador');

        // Render (y la mayoría de PaaS) terminan el HTTPS antes del contenedor,
        // por lo que las peticiones internas llegan como http. Sin esto, Laravel
        // genera URLs de assets en http:// y el navegador las bloquea (Mixed Content).
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
