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

// Email mensual de saldo a participantes bonificados
if (config('candidatos.email_saldo_bonificados.activo', true)) {
    $frecuenciaBonificados = config('candidatos.email_saldo_bonificados.frecuencia', 'monthly');
    $horaBonificados = config('candidatos.email_saldo_bonificados.hora', '10:00');
    $diaBonificados = config('candidatos.email_saldo_bonificados.dia_del_mes', 1);

    $cmdBonificados = Schedule::command('bonificados:enviar-email-saldo')
        ->timezone('Europe/Madrid')
        ->emailOutputOnFailure(config('candidatos.email_saldo_bonificados.email_errores'))
        ->onFailure(function () {
            \Log::error('Error al ejecutar el email mensual de saldo a bonificados');
        });

    match ($frecuenciaBonificados) {
        'weekly' => $cmdBonificados->weeklyOn(1, $horaBonificados),
        'biweekly' => $cmdBonificados->twiceMonthly(1, 15, $horaBonificados),
        default => $cmdBonificados->monthlyOn($diaBonificados, $horaBonificados),
    };
}
