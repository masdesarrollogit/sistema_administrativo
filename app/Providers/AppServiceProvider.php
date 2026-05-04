<?php

namespace App\Providers;

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
        if ($this->app->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Red de seguridad global: en cualquier entorno != production:
        //  1. Forzar TODOS los mailers SMTP a Mailpit (por si .env tiene Gmail real configurado)
        //  2. Redirigir TODOS los destinatarios a una dirección de pruebas
        // Esto evita envíos accidentales a destinatarios reales presentes en la BD.
        if (!$this->app->environment('production')) {
            $forceHost = env('MAIL_FORCE_HOST', 'mailpit');
            $forcePort = (int) env('MAIL_FORCE_PORT', 1025);

            // Sobrescribe el host/port/encryption/auth de cada mailer SMTP para que
            // ningún correo escape al SMTP real (Gmail, etc.) durante pruebas.
            $mailers = config('mail.mailers', []);
            foreach ($mailers as $name => $cfg) {
                if (($cfg['transport'] ?? null) === 'smtp') {
                    config([
                        "mail.mailers.{$name}.host" => $forceHost,
                        "mail.mailers.{$name}.port" => $forcePort,
                        "mail.mailers.{$name}.encryption" => null,
                        "mail.mailers.{$name}.username" => null,
                        "mail.mailers.{$name}.password" => null,
                    ]);
                }
            }

            \Illuminate\Support\Facades\Mail::alwaysTo(env('MAIL_FORCE_TO', 'pruebas@webcurso.local'));
        }
    }
}
