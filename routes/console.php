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

// Reportes Moodle Fase 1 — Snapshot diario y notificaciones
Schedule::command('reportes-moodle:snapshot')
    ->dailyAt(config('reportes_moodle.snapshot.cron_hora', '02:00'))
    ->timezone('Europe/Madrid')
    ->onFailure(function () {
        \Log::error('Error al ejecutar el cron snapshot de reportes Moodle');
    });

Schedule::command('reportes-moodle:notificar-no-conectados')
    ->dailyAt(config('reportes_moodle.reporte_no_conectados.cron_alumno_hora', '10:00'))
    ->timezone('Europe/Madrid')
    ->onFailure(function () {
        \Log::error('Error al ejecutar notificación a alumnos no conectados');
    });

Schedule::command('reportes-moodle:notificar-inactivos')
    ->dailyAt(config('reportes_moodle.reporte_inactivos.cron_alumno_hora', '10:15'))
    ->timezone('Europe/Madrid')
    ->onFailure(function () {
        \Log::error('Error al ejecutar notificación a alumnos inactivos (Fase 2)');
    });

Schedule::command('reportes-moodle:notificar-riesgo-critico')
    ->dailyAt(config('reportes_moodle.reporte_riesgo_critico.cron_alumno_hora', '10:30'))
    ->timezone('Europe/Madrid')
    ->onFailure(function () {
        \Log::error('Error al ejecutar notificación a alumnos en riesgo crítico (Fase 3)');
    });

Schedule::command('reportes-moodle:notificar-pre-cierre')
    ->dailyAt(config('reportes_moodle.reporte_pre_cierre.cron_alumno_hora', '10:45'))
    ->timezone('Europe/Madrid')
    ->onFailure(function () {
        \Log::error('Error al ejecutar notificación de Pre-cierre (Fase 4)');
    });

Schedule::command('reportes-moodle:notificar-apto-sin-examen')
    ->dailyAt(config('reportes_moodle.reporte_apto_sin_examen.cron_alumno_hora', '11:00'))
    ->timezone('Europe/Madrid')
    ->onFailure(function () {
        \Log::error('Error al ejecutar notificación de Apto sin examen (Fase 5)');
    });

Schedule::command('reportes-moodle:notificar-apto')
    ->dailyAt(config('reportes_moodle.reporte_apto.cron_alumno_hora', '11:15'))
    ->timezone('Europe/Madrid')
    ->onFailure(function () {
        \Log::error('Error al ejecutar notificación de Apto / Finalizado (Fase 6)');
    });

Schedule::command('reportes-moodle:detectar-no-aptos')
    ->dailyAt(config('reportes_moodle.reporte_no_aptos.cron_detectar_hora', '01:30'))
    ->timezone('Europe/Madrid')
    ->onFailure(function () {
        \Log::error('Error al ejecutar detección de No aptos (Fase 7)');
    });

Schedule::command('reportes-moodle:notificar-no-aptos')
    ->dailyAt(config('reportes_moodle.reporte_no_aptos.cron_notificar_hora', '11:30'))
    ->timezone('Europe/Madrid')
    ->onFailure(function () {
        \Log::error('Error al ejecutar notificación de No aptos / reinicio (Fase 7)');
    });

Schedule::command('reportes-moodle:notificar-tutores')
    ->weeklyOn(1, config('reportes_moodle.reporte_no_conectados.cron_tutor_hora_lunes', '09:00'))
    ->timezone('Europe/Madrid')
    ->onFailure(function () {
        \Log::error('Error al ejecutar notificación semanal a tutores');
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

// Sincronización de contratos de encomienda del sistema externo (cada 30 min)
Schedule::command('encomienda:sincronizar')
    ->everyThirtyMinutes()
    ->timezone('Europe/Madrid')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Log::error('Error al ejecutar el cron de sincronización de encomienda');
    });

// Lectura IMAP de respuestas del cuestionario de calidad (Power Automate → correo)
// Protegido por encuesta_calidad.imap_enabled (OFF en dev para no tocar el buzón real)
Schedule::command('encuestas-calidad:leer-imap')
    ->everyTenMinutes()
    ->timezone('Europe/Madrid')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Log::error('Error al ejecutar el cron de lectura IMAP de encuestas de calidad');
    });

// Índice inverso email→cursos de Moodle (incl. matrículas caducadas) para poder
// resolver el curso de encuestas de calidad de alumnos sin ficha/curso en el Panel.
// Diario de madrugada; el backfill (encuestas-calidad:resolver-moodle) se lanza aparte.
Schedule::command('encuestas-calidad:snapshot-moodle')
    ->dailyAt(config('encuesta_calidad.snapshot_moodle_hora', '03:30'))
    ->timezone('Europe/Madrid')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Log::error('Error al ejecutar el snapshot del índice de matrículas Moodle (encuestas de calidad)');
    });
