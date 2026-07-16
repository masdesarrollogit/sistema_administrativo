<?php

use App\Console\Commands\LeerEncuestasImap;
use App\Models\EncuestaCalidad;
use App\Services\Webcurso\EncuestaCalidadService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function cuerpoCorreo(string $token, string $formsId = 'resp-1', int $satisfaccion = 4): string
{
    $json = json_encode([
        'token'                => $token,
        'forms_id'             => $formsId,
        'alumno_nombre'        => 'ANA GARCIA',
        'alumno_email'         => 'ana@empresa.com',
        'numero_accion'        => '7',
        'numero_grupo'         => '2',
        'denominacion_accion'  => 'Excel avanzado',
        'fecha'                => '5/12/2026',
        'observaciones'        => 'Todo perfecto',
        'satisfaccion_general' => $satisfaccion,
        'item_01'              => 4,
    ]);

    return "Hola,\n\nNueva respuesta:\n\n===ENCUESTA-CALIDAD-JSON===\n{$json}\n===FIN===\n\nSaludos.";
}

beforeEach(function () {
    config(['encuesta_calidad.token' => 'secreto-123']);
    config(['encuesta_calidad.remitente_esperado' => null]);
});

it('procesa un correo válido de Power Automate y guarda la respuesta', function () {
    $cmd = app(LeerEncuestasImap::class);
    $res = $cmd->procesarCuerpo(cuerpoCorreo('secreto-123'), 'flujo@webcurso.es', new EncuestaCalidadService());

    expect($res['ok'])->toBeTrue();

    $e = EncuestaCalidad::where('forms_id', 'resp-1')->first();
    expect($e)->not->toBeNull();
    expect($e->origen)->toBe('power_automate');
    expect($e->satisfaccion_general)->toBe(4);
    expect($e->alumno_email)->toBe('ana@empresa.com');
    // 5/12/2026 en formato americano (M/D/Y) = 12 de mayo de 2026
    expect($e->fecha_cumplimentacion->format('Y-m-d'))->toBe('2026-05-12');
});

it('rechaza un correo con token inválido', function () {
    $cmd = app(LeerEncuestasImap::class);
    $res = $cmd->procesarCuerpo(cuerpoCorreo('token-malo'), 'flujo@webcurso.es', new EncuestaCalidadService());

    expect($res['ok'])->toBeFalse();
    expect(EncuestaCalidad::count())->toBe(0);
});

it('rechaza un correo sin bloque JSON', function () {
    $cmd = app(LeerEncuestasImap::class);
    $res = $cmd->procesarCuerpo('Correo cualquiera sin datos', null, new EncuestaCalidadService());

    expect($res['ok'])->toBeFalse();
    expect(EncuestaCalidad::count())->toBe(0);
});

it('rechaza remitente no autorizado cuando está configurado', function () {
    config(['encuesta_calidad.remitente_esperado' => 'flujo@webcurso.es']);

    $cmd = app(LeerEncuestasImap::class);
    $res = $cmd->procesarCuerpo(cuerpoCorreo('secreto-123'), 'otro@dominio.com', new EncuestaCalidadService());

    expect($res['ok'])->toBeFalse();
    expect(EncuestaCalidad::count())->toBe(0);
});

it('es idempotente por forms_id', function () {
    $cmd = app(LeerEncuestasImap::class);
    $service = new EncuestaCalidadService();
    $cmd->procesarCuerpo(cuerpoCorreo('secreto-123', 'resp-9'), null, $service);
    $cmd->procesarCuerpo(cuerpoCorreo('secreto-123', 'resp-9'), null, $service);

    expect(EncuestaCalidad::where('forms_id', 'resp-9')->count())->toBe(1);
});
