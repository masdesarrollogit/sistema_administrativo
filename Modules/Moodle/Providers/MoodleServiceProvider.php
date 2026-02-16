<?php

namespace Modules\Moodle\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Moodle\Services\MoodleService;

use Livewire\Livewire;

class MoodleServiceProvider extends ServiceProvider
{
    /**
     * Registra servicios en el contenedor.
     */
    public function register(): void
    {
        $this->app->singleton(MoodleService::class, function ($app) {
            return new MoodleService();
        });
    }

    /**
     * Ejecuta lógica al arrancar el módulo.
     */
    public function boot(): void
    {
        // Registrar componentes Livewire
        Livewire::component('moodle-user-management', \Modules\Moodle\Http\Livewire\UserManagement::class);

        // Cargar rutas si existen
        if (file_exists(__DIR__ . '/../Routes/web.php')) {
            $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        }

        // Cargar vistas si existen
        if (is_dir(__DIR__ . '/../Resources/views')) {
            $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'moodle');
        }
    }
}
