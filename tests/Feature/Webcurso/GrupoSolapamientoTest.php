<?php

use App\Livewire\Webcurso\MatriculacionPanel;
use App\Models\AccionFormativa;
use App\Models\Alumno;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\GrupoFormativo;
use App\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Excepción por grupo a la regla de no solapamiento: un grupo con
 * `permite_solapamiento = true` admite alumnos que ya están en otro grupo con
 * fechas solapadas, y tampoco bloquea altas en otros grupos.
 * Caso real: Drivania, Claude Code Avanzado Agentes (24/10–31/12/2026).
 */

// TipoCandidatoFactory usa un `codigo` fijo y único: todos los grupos de un test
// deben colgar del mismo candidato.
function alumnoConGrupoEnCurso(): array
{
    $empresa   = Empresa::factory()->create();
    $candidato = Candidato::factory()->create(['empresa_id' => $empresa->id, 'estatus' => 'completo']);
    $alumno    = Alumno::factory()->create(['empresa_id' => $empresa->id]);

    $grupo = GrupoFormativo::factory()->create([
        'candidato_id' => $candidato->id,
        'empresa_id'   => $empresa->id,
        'fecha_inicio' => now()->subDays(10)->toDateString(),
        'fecha_fin'    => now()->addDays(60)->toDateString(),
        'estado'       => 'en_curso',
    ]);
    $grupo->alumnos()->attach($alumno);

    return [$alumno, $candidato];
}

function grupoDestino(Candidato $candidato, bool $permiteSolapamiento): GrupoFormativo
{
    return GrupoFormativo::factory()->create([
        'candidato_id'         => $candidato->id,
        'empresa_id'           => $candidato->empresa_id,
        'fecha_inicio'         => now()->addDays(5)->toDateString(),
        'fecha_fin'            => now()->addDays(40)->toDateString(),
        'estado'               => 'abierto',
        'permite_solapamiento' => $permiteSolapamiento,
    ]);
}

it('sin la casilla, el alumno con un grupo solapado sigue bloqueado', function () {
    [$alumno, $candidato] = alumnoConGrupoEnCurso();
    $destino = grupoDestino($candidato, permiteSolapamiento: false);

    expect($alumno->tieneGrupoActivoEnPeriodo($destino->fecha_inicio, $destino->fecha_fin, $destino->id))->toBeTrue();
});

it('con la casilla en el grupo destino, el alumno entra', function () {
    [$alumno, $candidato] = alumnoConGrupoEnCurso();
    $destino = grupoDestino($candidato, permiteSolapamiento: true);

    expect($alumno->tieneGrupoActivoEnPeriodo($destino->fecha_inicio, $destino->fecha_fin, $destino->id))->toBeFalse();
});

it('un grupo con la casilla no bloquea altas en otro grupo normal', function () {
    $empresa   = Empresa::factory()->create();
    $candidato = Candidato::factory()->create(['empresa_id' => $empresa->id, 'estatus' => 'completo']);
    $alumno    = Alumno::factory()->create(['empresa_id' => $empresa->id]);

    $excepcional = grupoDestino($candidato, permiteSolapamiento: true);
    $excepcional->alumnos()->attach($alumno);

    $normal = grupoDestino($candidato, permiteSolapamiento: false);

    expect($alumno->tieneGrupoActivoEnPeriodo($normal->fecha_inicio, $normal->fecha_fin, $normal->id))->toBeFalse();
});

it('crea el grupo con la casilla marcada y añade al alumno solapado desde el panel', function () {
    $empresa   = Empresa::factory()->create();
    $candidato = Candidato::factory()->create(['empresa_id' => $empresa->id, 'estatus' => 'completo']);
    $alumno    = Alumno::factory()->create(['empresa_id' => $empresa->id]);

    $enCurso = GrupoFormativo::factory()->create([
        'candidato_id' => $candidato->id,
        'empresa_id'   => $empresa->id,
        'fecha_inicio' => now()->subDays(10)->toDateString(),
        'fecha_fin'    => now()->addDays(60)->toDateString(),
        'estado'       => 'en_curso',
    ]);
    $enCurso->alumnos()->attach($alumno);

    $accion = AccionFormativa::factory()->create();
    $tutor  = Tutor::factory()->create();

    $panel = Livewire::test(MatriculacionPanel::class, ['candidato' => $candidato])
        ->set('nuevaAccionFormativaId', $accion->id)
        ->set('nuevoTutorId', $tutor->id)
        ->set('nuevoTramo', 'tramo_2')
        ->set('nuevaFechaInicio', now()->addDays(5)->toDateString())
        ->set('nuevaFechaFin', now()->addDays(40)->toDateString())
        ->set('nuevaJornadaLaboral', 1)
        ->set('nuevoPermiteSolapamiento', true)
        ->call('crearGrupo')
        ->assertHasNoErrors();

    $grupo = GrupoFormativo::where('accion_formativa_id', $accion->id)->firstOrFail();
    expect($grupo->permite_solapamiento)->toBeTrue();

    $panel->call('agregarAlumnosAlGrupo', $alumno->id);

    expect($grupo->alumnos()->whereKey($alumno->id)->exists())->toBeTrue();
});
