<?php

use App\Livewire\Webcurso\MatriculacionPanel;
use App\Models\AccionFormativa;
use App\Models\Alumno;
use App\Models\AlumnoProgresoMoodle;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\GrupoFormativo;
use App\Models\MatriculaAutonoma;
use App\Models\TipoCandidato;
use App\Models\Tutor;
use App\Services\Webcurso\AlumnoProgresoSnapshotter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Particulares: alumnos que pagan el curso de su bolsillo. No hay bonificación, así que
 * no llevan grupo formativo — se matriculan igual que un autónomo 2x1. Pero sí estudian
 * en nuestra aula, de modo que su seguimiento académico debe funcionar como el de un
 * bonificado (ver OrigenMatriculaMoodle).
 */

function candidatoParticular(): Candidato
{
    $tipo = TipoCandidato::factory()->create([
        'codigo' => 'particular',
        'nombre' => 'Particular',
    ]);

    return Candidato::factory()->create([
        'tipo_candidato_id'  => $tipo->id,
        'empresa_id'         => null,
        'empresa_externa_id' => null,
        'estatus'            => 'completo',
    ]);
}

it('matricula a un particular sin empresa y guarda su empresa como texto', function () {
    $candidato = candidatoParticular();
    $accion = AccionFormativa::factory()->create();
    $tutor = Tutor::factory()->create();

    $empresasAntes = Empresa::count();

    Livewire::test(MatriculacionPanel::class, ['candidato' => $candidato])
        ->call('abrirFormAutonomo')
        ->set('autonomoAccionFormativaId', $accion->id)
        ->set('autonomoTutorId', $tutor->id)
        ->set('autonomoNuevoAlumno', true)
        ->set('autonomoNombre', 'Henry')
        ->set('autonomoApellido1', 'Prueba')
        ->set('autonomoNif', '99887766X')
        ->set('autonomoEmail', 'henry@example.com')
        ->set('autonomoEmpresaTexto', 'Talleres Pepe SL')
        // Fechas obligatorias, pero el inicio es libre: puede ser mañana mismo
        ->set('autonomoFechaInicio', now()->addDay()->toDateString())
        ->set('autonomoFechaFin', now()->addDays(31)->toDateString())
        ->call('crearMatriculaAutonoma')
        ->assertHasNoErrors();

    $matricula = MatriculaAutonoma::where('candidato_id', $candidato->id)->firstOrFail();
    $alumno = $matricula->alumno;

    expect($matricula->modalidad)->toBe(MatriculaAutonoma::MODALIDAD_PARTICULAR)
        ->and($matricula->esParticular())->toBeTrue()
        ->and($matricula->empresa_id)->toBeNull()
        ->and($alumno->empresa_id)->toBeNull()
        ->and($alumno->empresa_texto)->toBe('Talleres Pepe SL')
        // La empresa declarada es solo informativa: no se registra como empresa cliente
        ->and(Empresa::count())->toBe($empresasAntes);
});

it('exige fechas de inicio y fin en la matrícula individual', function () {
    $candidato = candidatoParticular();

    Livewire::test(MatriculacionPanel::class, ['candidato' => $candidato])
        ->call('abrirFormAutonomo')
        ->set('autonomoAccionFormativaId', AccionFormativa::factory()->create()->id)
        ->set('autonomoTutorId', Tutor::factory()->create()->id)
        ->set('autonomoNuevoAlumno', true)
        ->set('autonomoNombre', 'Henry')
        ->set('autonomoApellido1', 'Prueba')
        ->set('autonomoNif', '99887766X')
        ->set('autonomoEmail', 'henry@example.com')
        ->call('crearMatriculaAutonoma')
        ->assertHasErrors(['autonomoFechaInicio', 'autonomoFechaFin']);

    expect(MatriculaAutonoma::count())->toBe(0);
});

it('no muestra grupos formativos a un candidato particular', function () {
    $candidato = candidatoParticular();

    $html = Livewire::test(MatriculacionPanel::class, ['candidato' => $candidato])->html();

    expect($html)->not->toContain('Grupos Formativos')
        ->and($html)->toContain('Particulares');
});

it('el filtro Autónomos no devuelve particulares ni al revés', function () {
    // Comparten tabla, pero son cosas distintas: el 2x1 es gratis y de empresa,
    // el particular lo paga el alumno y no tiene empresa.
    $empresa = Empresa::factory()->create();
    $candidato = Candidato::factory()->create(['empresa_id' => $empresa->id]);
    $accion = AccionFormativa::factory()->create();
    $tutor = Tutor::factory()->create();

    $autonomo = Alumno::factory()->create(['empresa_id' => $empresa->id]);
    $particular = Alumno::factory()->create(['empresa_id' => null, 'empresa_texto' => 'Talleres Pepe SL']);

    foreach ([[$autonomo, MatriculaAutonoma::MODALIDAD_AUTONOMO, $empresa->id],
              [$particular, MatriculaAutonoma::MODALIDAD_PARTICULAR, null]] as [$alumno, $modalidad, $empresaId]) {
        MatriculaAutonoma::create([
            'candidato_id'        => $candidato->id,
            'modalidad'           => $modalidad,
            'alumno_id'           => $alumno->id,
            'accion_formativa_id' => $accion->id,
            'tutor_id'            => $tutor->id,
            'empresa_id'          => $empresaId,
            'fecha_inicio'        => now()->subDay()->toDateString(),
            'fecha_fin'           => now()->addDays(20)->toDateString(),
            'estado'              => 'matriculado',
        ]);
    }

    $idsDelFiltro = function (string $tipo) {
        return Livewire::test(\App\Livewire\Webcurso\AlumnosIndex::class)
            ->set('filtroAno', '')
            ->set('filtroTipo', $tipo)
            ->viewData('alumnos')
            ->pluck('id');
    };

    expect($idsDelFiltro('autonomo'))->toContain($autonomo->id)
        ->and($idsDelFiltro('autonomo'))->not->toContain($particular->id)
        ->and($idsDelFiltro('particular'))->toContain($particular->id)
        ->and($idsDelFiltro('particular'))->not->toContain($autonomo->id);
});

it('el snapshot de Reportes Moodle recorre grupos y matrículas individuales', function () {
    $empresa = Empresa::factory()->create();
    $candidato = Candidato::factory()->create(['empresa_id' => $empresa->id]);

    // Bonificado: grupo en curso con el alumno matriculado
    $grupo = GrupoFormativo::factory()->create([
        'candidato_id' => $candidato->id,
        'empresa_id'   => $empresa->id,
        'estado'       => 'en_curso',
        'moodle_course_id' => 500,
        'fecha_inicio' => now()->subDays(5)->toDateString(),
        'fecha_fin'    => now()->addDays(20)->toDateString(),
    ]);
    $bonificado = Alumno::factory()->create(['empresa_id' => $empresa->id]);
    $grupo->alumnos()->attach($bonificado, ['estado_moodle' => 'matriculado', 'moodle_user_id' => 11]);

    // Particular: matrícula individual activa
    $particular = Alumno::factory()->create(['empresa_id' => null, 'empresa_texto' => 'Talleres Pepe SL']);
    $matricula = MatriculaAutonoma::create([
        'candidato_id'        => $candidato->id,
        'modalidad'           => MatriculaAutonoma::MODALIDAD_PARTICULAR,
        'alumno_id'           => $particular->id,
        'accion_formativa_id' => AccionFormativa::factory()->create()->id,
        'tutor_id'            => Tutor::factory()->create()->id,
        'empresa_id'          => null,
        'fecha_inicio'        => now()->subDays(2)->toDateString(),
        'fecha_fin'           => now()->addDays(15)->toDateString(),
        'moodle_course_id'    => 600,
        'moodle_user_id'      => 22,
        'estado'              => 'matriculado',
    ]);

    $snapshotter = app(AlumnoProgresoSnapshotter::class);
    $metodo = new ReflectionMethod($snapshotter, 'seleccionarMatriculas');
    $metodo->setAccessible(true);
    $origenes = $metodo->invoke($snapshotter, null);

    $claves = $origenes->map(fn ($o) => $o->claveOrigen())->all();

    expect($claves)->toContain("grupo:{$grupo->id}")
        ->and($claves)->toContain("autonoma:{$matricula->id}");
});

it('red de seguridad: una matrícula individual sin fechas no entra al snapshot', function () {
    // El formulario ya exige ambas fechas. Este guard protege de datos antiguos o de
    // inserciones directas: sin ventana no se puede calcular el % de tiempo (R3) ni el
    // pre-cierre (R4), así que el alumno se queda fuera en lugar de generar métricas falsas.
    $candidato = Candidato::factory()->create();
    MatriculaAutonoma::create([
        'candidato_id'        => $candidato->id,
        'modalidad'           => MatriculaAutonoma::MODALIDAD_AUTONOMO,
        'alumno_id'           => Alumno::factory()->create()->id,
        'accion_formativa_id' => AccionFormativa::factory()->create()->id,
        'tutor_id'            => Tutor::factory()->create()->id,
        'fecha_inicio'        => null,
        'fecha_fin'           => null,
        'moodle_course_id'    => 600,
        'moodle_user_id'      => 22,
        'estado'              => 'matriculado',
    ]);

    $snapshotter = app(AlumnoProgresoSnapshotter::class);
    $metodo = new ReflectionMethod($snapshotter, 'seleccionarMatriculas');
    $metodo->setAccessible(true);

    expect($metodo->invoke($snapshotter, null))->toBeEmpty();
});

it('resuelve tutor, acción y etiqueta de grupo desde cualquier origen', function () {
    $tutor = Tutor::factory()->create(['nombre' => 'Raquel']);
    $accion = AccionFormativa::factory()->create(['numero_accion' => 700]);
    $alumno = Alumno::factory()->create(['empresa_id' => null]);

    $matricula = MatriculaAutonoma::create([
        'candidato_id'        => Candidato::factory()->create()->id,
        'modalidad'           => MatriculaAutonoma::MODALIDAD_PARTICULAR,
        'alumno_id'           => $alumno->id,
        'accion_formativa_id' => $accion->id,
        'tutor_id'            => $tutor->id,
        'fecha_inicio'        => now()->subDay()->toDateString(),
        'fecha_fin'           => now()->addDays(10)->toDateString(),
        'estado'              => 'matriculado',
    ]);

    $snap = AlumnoProgresoMoodle::create([
        'alumno_id'             => $alumno->id,
        'matricula_autonoma_id' => $matricula->id,
        'fecha_snapshot'        => now()->toDateString(),
    ]);

    expect($snap->tutor_curso?->id)->toBe($tutor->id)
        ->and($snap->accion_curso?->id)->toBe($accion->id)
        // Una matrícula individual no tiene código de grupo FUNDAE: se etiqueta la modalidad
        ->and($snap->codigo_grupo)->toBe('Particular')
        ->and($snap->tipo_matricula)->toBe('particular')
        ->and($snap->columnasOrigenLog())->toBe([
            'grupo_formativo_id'    => null,
            'matricula_autonoma_id' => $matricula->id,
        ]);
});
