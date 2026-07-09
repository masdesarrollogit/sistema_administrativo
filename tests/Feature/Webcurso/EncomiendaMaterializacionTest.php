<?php

use App\Livewire\Webcurso\MatriculacionPanel;
use App\Models\Alumno;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\EncomiendaAlumnoStaging;
use App\Models\EncomiendaContrato;
use App\Models\GrupoFormativo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Desactiva la descarga de PDF en tests (no debe salir a la red)
    config(['encomienda.pdf.base_url' => null, 'encomienda.pdf.token' => null]);
});

function stagingDe(Empresa $empresa, Candidato $candidato): EncomiendaAlumnoStaging
{
    $contrato = EncomiendaContrato::create([
        'source_id'            => 999,
        'referencia_aceptacion' => 'CE-20260101-abcdef',
        'empresa_cif'          => $empresa->cif,
        'empresa_id'           => $empresa->id,
        'candidato_id'         => $candidato->id,
        'estado_procesamiento' => 'candidato_creado',
    ]);

    return EncomiendaAlumnoStaging::create([
        'source_id'             => 5001,
        'encomienda_contrato_id' => $contrato->id,
        'contrato_source_id'    => 999,
        'nombre'                => 'Blanca',
        'apellido1'             => 'González',
        'nif'                   => '74520699D',
        'email'                 => 'blanca@example.com',
        'nivel_estudios'        => 6,
        'categoria_profesional' => 1,
        'grupo_cotizacion_tgss' => '7',
        'estado'                => 'pendiente',
    ]);
}

it('materializa un alumno de encomienda: crea Alumno, lo adjunta al grupo y marca staging', function () {
    $empresa = Empresa::factory()->create();
    $candidato = Candidato::factory()->create(['empresa_id' => $empresa->id]);
    $grupo = GrupoFormativo::factory()->create([
        'candidato_id' => $candidato->id,
        'empresa_id'   => $empresa->id,
        'estado'       => 'abierto',
        'fecha_inicio' => now()->addDays(10)->toDateString(),
        'fecha_fin'    => now()->addDays(40)->toDateString(),
    ]);
    $stg = stagingDe($empresa, $candidato);

    Livewire::test(MatriculacionPanel::class, ['candidato' => $candidato])
        ->call('materializarAlumnoEncomienda', $grupo->id, $stg->id);

    $alumno = Alumno::where('nif', '74520699D')->where('empresa_id', $empresa->id)->first();
    expect($alumno)->not->toBeNull();
    expect($alumno->nivel_estudios)->toBe(6);
    expect($alumno->categoria_profesional)->toBe(1);
    expect($alumno->grupo_cotizacion_tgss)->toBe('7');

    expect($grupo->fresh()->alumnos()->where('alumno_id', $alumno->id)->exists())->toBeTrue();

    $stg->refresh();
    expect($stg->estado)->toBe('materializado');
    expect($stg->alumno_id)->toBe($alumno->id);
});

it('no materializa dos veces el mismo alumno de encomienda', function () {
    $empresa = Empresa::factory()->create();
    $candidato = Candidato::factory()->create(['empresa_id' => $empresa->id]);
    $grupo = GrupoFormativo::factory()->create([
        'candidato_id' => $candidato->id,
        'empresa_id'   => $empresa->id,
        'estado'       => 'abierto',
        'fecha_inicio' => now()->addDays(10)->toDateString(),
        'fecha_fin'    => now()->addDays(40)->toDateString(),
    ]);
    $stg = stagingDe($empresa, $candidato);

    $component = Livewire::test(MatriculacionPanel::class, ['candidato' => $candidato]);
    $component->call('materializarAlumnoEncomienda', $grupo->id, $stg->id);
    $component->call('materializarAlumnoEncomienda', $grupo->id, $stg->id);

    // Solo un alumno con ese NIF; el segundo intento no rompe nada
    expect(Alumno::where('nif', '74520699D')->count())->toBe(1);
    expect($stg->fresh()->estado)->toBe('materializado');
});
