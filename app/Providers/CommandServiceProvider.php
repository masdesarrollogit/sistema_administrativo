<?php

namespace App\Providers;

use App\Console\Commands\EnviarRecordatoriosCandidatos;
use Illuminate\Support\ServiceProvider;

class CommandServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->commands([
            EnviarRecordatoriosCandidatos::class,
        ]);
    }
}
