<?php

namespace App\Providers;

use App\Enums\Ability;
use App\Health\ReadinessChecker;
use App\Models\User;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

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
        // Sin tokens bearer (ADR-0001, design D1): Sanctum solo autentica por la sesión de la SPA.
        Sanctum::getAccessTokenFromRequestUsing(fn (): ?string => null);

        // Cada capacidad es un Gate que consulta el mapa con el rol leído de la base (design D4).
        // Sin Gate::before: admin no es superusuario.
        foreach (Ability::cases() as $ability) {
            Gate::define($ability->value, fn (User $user): bool => $user->role->allows($ability->value));
        }
    }
}
