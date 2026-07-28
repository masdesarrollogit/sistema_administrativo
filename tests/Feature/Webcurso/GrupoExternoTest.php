<?php

use App\Livewire\Webcurso\MatriculacionPanel;
use App\Models\AccionFormativa;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\EmpresaExterna;
use App\Models\Alumno;
use App\Models\GrupoFormativo;
use App\Models\TipoCandidato;
use App\Models\Tutor;
use App\Services\Webcurso\FundaeXmlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Grupos formativos de gestión externa: la empresa cliente comunica la acción y el
 * grupo en SU aplicativo FUNDAE y solo nos traslada el código (ej. "241/3").
 * Nosotros impartimos el curso en Moodle, así que el alumno debe entrar en el mismo
 * circuito de seguimiento que un bonificado propio, pero sin ningún trámite FUNDAE nuestro.
 */

function candidatoExterno(): Candidato
{
    $tipo = TipoCandidato::factory()->create([
        'codigo' => 'empresa_externa',
        'nombre' => 'Empresa Externa',
    ]);

    $externa = EmpresaExterna::create([
        'cif'          => 'A123456789',
        'razon_social' => 'Maquinaria SL',
        'email'        => 'rrhh@maquinaria.example',
    ]);

    return Candidato::factory()->create([
        'tipo_candidato_id'  => $tipo->id,
        'empresa_id'         => null,
        'empresa_externa_id' => $externa->id,
        'estatus'            => 'completo',
    ]);
}

it('crea el grupo externo con fecha de inicio pasada y sin consumir ID de nuestra FUNDAE', function () {
    $candidato = candidatoExterno();
    $accion = AccionFormativa::factory()->create(['numero_accion' => 500]);
    $tutor  = Tutor::factory()->create();

    Livewire::test(MatriculacionPanel::class, ['candidato' => $candidato])
        ->set('nuevaAccionFormativaId', $accion->id)
        ->set('nuevoTutorId', $tutor->id)
        ->set('nuevoTramo', 'tramo_2')
        ->set('nuevoCodigoGrupoExterno', '241/3')
        // La empresa nos avisa tarde: el curso ya empezó la semana pasada.
        ->set('nuevaFechaInicio', now()->subDays(7)->toDateString())
        ->set('nuevaFechaFin', now()->addDays(20)->toDateString())
        ->set('nuevaJornadaLaboral', 1)
        ->call('crearGrupo')
        ->assertHasNoErrors();

    $grupo = GrupoFormativo::where('candidato_id', $candidato->id)->firstOrFail();

    expect($grupo->gestion_externa)->toBeTrue()
        ->and($grupo->codigo_grupo_externo)->toBe('241/3')
        ->and($grupo->id_grupo_fundae)->toBeNull()
        ->and($grupo->codigo_grupo_moodle)->toBe('241/3');
});

it('nunca da de alta la empresa externa en el listado de empresas', function () {
    // No calculamos su crédito FUNDAE: aparecer en /webcurso/empresas con saldo cero
    // sería un dato inventado. Sus datos viven solo en `empresas_externas`.
    $candidato = candidatoExterno();
    $accion = AccionFormativa::factory()->create();
    $tutor = Tutor::factory()->create();
    $empresasAntes = Empresa::count();

    Livewire::test(MatriculacionPanel::class, ['candidato' => $candidato])
        ->set('nuevaAccionFormativaId', $accion->id)
        ->set('nuevoTutorId', $tutor->id)
        ->set('nuevoCodigoGrupoExterno', '241/3')
        ->set('nuevaFechaInicio', now()->toDateString())
        ->set('nuevaFechaFin', now()->addDays(20)->toDateString())
        ->call('crearGrupo')
        ->assertHasNoErrors()
        // Alta de alumno: tampoco debe crear empresa
        ->set('alumnoNombre', 'Greicy')
        ->set('alumnoApellido1', 'Barreto')
        ->set('alumnoNif', '11223344A')
        ->set('alumnoEmail', 'greicy@example.com')
        ->call('guardarAlumno')
        ->assertHasNoErrors();

    $grupo = GrupoFormativo::where('candidato_id', $candidato->id)->firstOrFail();
    $alumno = Alumno::where('nif', '11223344A')->firstOrFail();

    expect(Empresa::count())->toBe($empresasAntes)
        ->and(Empresa::where('cif', 'A123456789')->exists())->toBeFalse()
        ->and($grupo->empresa_id)->toBeNull()
        ->and($alumno->empresa_id)->toBeNull()
        // La razón social queda como texto informativo, leída de `empresas_externas`
        ->and($alumno->empresa_texto)->toBe('Maquinaria SL')
        // Y el grupo sigue sabiendo de qué empresa es, vía el candidato
        ->and($grupo->empresa_nombre)->toBe('Maquinaria SL')
        ->and($grupo->empresa_cif)->toBe('A123456789');
});

it('exige el código de acción/grupo que facilita la empresa', function () {
    $candidato = candidatoExterno();
    $accion = AccionFormativa::factory()->create();
    $tutor  = Tutor::factory()->create();

    Livewire::test(MatriculacionPanel::class, ['candidato' => $candidato])
        ->set('nuevaAccionFormativaId', $accion->id)
        ->set('nuevoTutorId', $tutor->id)
        ->set('nuevaFechaInicio', now()->addDays(5)->toDateString())
        ->set('nuevaFechaFin', now()->addDays(35)->toDateString())
        ->call('crearGrupo')
        ->assertHasErrors('nuevoCodigoGrupoExterno');

    expect(GrupoFormativo::count())->toBe(0);
});

it('asignarIdGrupoFundae no numera los grupos externos', function () {
    $accion = AccionFormativa::factory()->create(['numero_accion' => 300]);
    // Un solo candidato: TipoCandidatoFactory usa un código único fijo.
    $candidato = Candidato::factory()->create();

    $externo = GrupoFormativo::factory()->create([
        'candidato_id'         => $candidato->id,
        'accion_formativa_id'  => $accion->id,
        'gestion_externa'      => true,
        'codigo_grupo_externo' => '300/9',
    ]);
    $externo->asignarIdGrupoFundae();

    expect($externo->fresh()->id_grupo_fundae)->toBeNull();

    // Un grupo propio de la misma acción arranca en 1: el externo no gastó número.
    $propio = GrupoFormativo::factory()->create([
        'candidato_id'        => $candidato->id,
        'accion_formativa_id' => $accion->id,
    ]);
    $propio->asignarIdGrupoFundae();

    expect($propio->fresh()->id_grupo_fundae)->toBe(1);
});

it('mantiene abierto un grupo externo dentro de los 2 días previos al inicio', function () {
    $candidato = Candidato::factory()->create();

    $externo = GrupoFormativo::factory()->create([
        'candidato_id'    => $candidato->id,
        'gestion_externa' => true,
        'estado'          => 'abierto',
        'fecha_inicio'    => now()->addDay()->toDateString(),
        'fecha_fin'       => now()->addDays(30)->toDateString(),
    ]);

    $propio = GrupoFormativo::factory()->create([
        'candidato_id' => $candidato->id,
        'estado'       => 'abierto',
        'fecha_inicio' => now()->addDay()->toDateString(),
        'fecha_fin'    => now()->addDays(30)->toDateString(),
    ]);

    expect($externo->estaAbierto())->toBeTrue()
        ->and($propio->estaAbierto())->toBeFalse();

    // El scope aplica el mismo criterio que el método.
    $abiertos = GrupoFormativo::abiertos()->pluck('id');
    expect($abiertos)->toContain($externo->id)
        ->and($abiertos)->not->toContain($propio->id);
});

it('rechaza generar XML de inicio para un grupo externo', function () {
    $externo = GrupoFormativo::factory()->create([
        'gestion_externa'      => true,
        'codigo_grupo_externo' => '241/3',
    ]);

    expect(fn () => (new FundaeXmlService())->generarXmlInicioGrupo([$externo->id]))
        ->toThrow(InvalidArgumentException::class);
});
