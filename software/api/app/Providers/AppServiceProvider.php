<?php

namespace App\Providers;

use App\Health\ReadinessChecker;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // El migrador se registra solo con el alias 'migrator', sin enlace por clase.
        $this->app->when(ReadinessChecker::class)
            ->needs(Migrator::class)
            ->give(fn ($app): Migrator => $app->make('migrator'));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
