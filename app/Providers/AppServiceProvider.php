<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });

        Gate::define('viewApiDocs', function (?User $user) {
            if (app()->environment('local')) {
                return true;
            }
            // Si te logueaste por la web, $user tendrá el objeto del super-admin
            return $user && $user->hasRole('super-admin');
        });

        Scramble::extendOpenApi(function (OpenApi $openApi) {
            // Personalizamos la descripción general de la API
            $openApi->info->description = "
            ## Documentación Oficial de la API
            Sesión de Administrador activa.

            [➔ Cerrar Sesión (Salir de la documentación)](" . route('logout.docs') . ")
        ";

            $openApi->secure(
                SecurityScheme::http('bearer')
            );
        });
    }
}
