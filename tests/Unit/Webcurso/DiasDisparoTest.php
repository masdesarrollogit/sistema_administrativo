<?php

use Carbon\CarbonImmutable;

/**
 * Replica la lógica de cálculo de días desde inicio del comando
 * NotificarNoConectadosCommand. Si el inicio aún no llegó debe dar
 * número negativo y quedar excluido del disparo. Si ya pasó, número positivo
 * y solo se dispara cuando coincide con [3, 6, 9].
 */
function diasDesdeInicio(string $hoy, string $fechaInicio): int
{
    $hoyD = CarbonImmutable::parse($hoy)->startOfDay();
    $inicio = CarbonImmutable::parse($fechaInicio)->startOfDay();
    return (int) $inicio->diffInDays($hoyD, false);
}

it('da 0 el mismo día de inicio', function () {
    expect(diasDesdeInicio('2026-05-07', '2026-05-07'))->toBe(0);
});

it('da días positivos cuando el inicio ya pasó', function () {
    expect(diasDesdeInicio('2026-05-07', '2026-05-04'))->toBe(3);
    expect(diasDesdeInicio('2026-05-07', '2026-05-02'))->toBe(5);
    expect(diasDesdeInicio('2026-05-07', '2026-04-29'))->toBe(8);
});

it('da días negativos cuando el inicio aún no llegó', function () {
    expect(diasDesdeInicio('2026-05-07', '2026-05-10'))->toBe(-3);
    expect(diasDesdeInicio('2026-05-07', '2026-05-25'))->toBeLessThan(0);
});

it('los valores del config son los acordados', function () {
    $config = require __DIR__ . '/../../../config/reportes_moodle.php';

    expect($config['reporte_no_conectados']['dias_disparo_alumno'])->toBe([3, 6, 9]);
    expect($config['reporte_no_conectados']['cron_alumno_hora'])->toBe('10:00');
    expect($config['reporte_no_conectados']['cron_tutor_hora_lunes'])->toBe('09:00');
    expect($config['reporte_no_conectados']['incluir_tutores_externos'])->toBeTrue();
    expect($config['snapshot']['cron_hora'])->toBe('02:00');
});
