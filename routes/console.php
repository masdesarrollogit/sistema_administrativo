<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Programar envío de recordatorios a candidatos (diario 9:00 AM España)
// Se ejecuta cada día; la frecuencia personalizada de cada candidato
// (campo frecuencia_envio) se evalúa internamente en scopeListosParaRecordatorio
Schedule::command('candidatos:enviar-recordatorios')
    ->dailyAt(config('candidatos.recordatorios.recordatorios_hora', '09:00'))
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
