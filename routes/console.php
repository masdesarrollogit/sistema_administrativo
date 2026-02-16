<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Programar envío de recordatorios a candidatos (Lunes 9:00 AM España)
Schedule::command('candidatos:enviar-recordatorios')
    ->weeklyOn(1, config('candidatos.recordatorios.recordatorios_hora', '09:00'))
    ->timezone('Europe/Madrid')
    ->emailOutputOnFailure(config('candidatos.recordatorios.email_errores'))
    ->onFailure(function () {
        \Log::error('Error al ejecutar el cron de recordatorios de candidatos');
    });

// Programar resumen de pendientes para admin (1:00 PM España)
Schedule::command('candidatos:enviar-resumen')
    ->dailyAt(config('candidatos.recordatorios.resumen_hora', '13:00'))
    ->timezone('Europe/Madrid')
    ->onFailure(function () {
        \Log::error('Error al ejecutar el cron de resumen de candidatos');
    });
