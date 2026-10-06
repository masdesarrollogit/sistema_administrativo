<?php

use App\Models\Alumno;
use App\Models\Empresa;
use App\Models\EncuestaCalidad;
use App\Models\MoodleMatriculaIndex;
use Modules\Moodle\Services\MoodleService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/** Respuesta tal como la devuelve mod_calidadfundae_get_responses. */
function respuestaPlugin(array $over = []): array
{
    $base = [
        'id' => 501, 'calidadfundaeid' => 3, 'cmid' => 77, 'instancename' => 'Cuestionario de calidad',
        'userid' => 900, 'useremail' => 'Aaa@Example.com', 'userfullname' => 'Alumno Aaa', 'username' => 'aaa@example.com',
        'courseid' => 42, 'coursefullname' => 'Claude Code 40h m', 'courseshortname' => 'CC',
        'coursestartdate' => strtotime('2026-09-01 00:00:00'), 'courseenddate' => strtotime('2026-09-30 00:00:00'),
        'status' => 1, 'timecreated' => strtotime('2026-09-29 10:00:00'), 'timemodified' => strtotime('2026-09-29 10:05:00'),
        'timesubmitted' => strtotime('2026-09-29 10:05:00'), 'fechacumplim' => strtotime('2026-09-29 00:00:00'),
        'origendatos' => 2, 'sugerencias' => 'Muy útil', 'groupid' => 5, 'modalidad' => 2, 'modalidad_label' => 'Teleformación',
        'expediente' => null, 'perfilacceso' => null, 'cif' => 'b12345678', 'naccion' => '18', 'ngrupo' => '21',
        'denominacion' => 'Claude Code 40h m', 'grupolabel' => '18/21',
        'edad' => '35', 'sexo' => '1', 'sexo_label' => 'Mujer', 'titulacion' => '1.1.1', 'titulacion_label' => 'Licenciatura',
        'provincia' => 'Barcelona', 'categoria' => '3', 'categoria_label' => 'Técnico', 'horario' => '1', 'horario_label' => 'Dentro de la jornada',
        'pctjornada' => '1', 'pctjornada_label' => 'Menos del 25%', 'tamanoempresa' => '2', 'tamanoempresa_label' => 'De 10 a 49',
        'v1_1' => 4, 'v1_2' => 3, 'v2_1' => 4, 'v2_2' => 2, 'v3_1' => 4, 'v3_2' => 9,
        'v4_1f' => null, 'v4_1t' => 4, 'v4_2f' => null, 'v4_2t' => 3,
        'v5_1' => 4, 'v5_2' => 4, 'v6_1' => null, 'v6_2' => null, 'v7_1' => 3, 'v7_2' => 4,
        's8_1' => 1, 's8_2' => 2, 'v9_1' => 3, 'v9_2' => 4, 'v9_3' => 2, 'v9_4' => 4, 'v9_5' => 4,
        'v10' => 4, 's10_1' => 1,
    ];

    return array_merge($base, $over);
}

/** Simula MoodleService devolviendo las páginas indicadas en orden. */
function moodleConPaginas(array ...$paginas)
{
    $mock = Mockery::mock(MoodleService::class);
    foreach ($paginas as $i => $respuestas) {
        $mock->shouldReceive('getCalidadFundaeResponses')->ordered()->once()->andReturn([
            'responses'    => $respuestas,
            'total'        => array_sum(array_map('count', $paginas)),
            'hasmore'      => $i < count($paginas) - 1,
            'lastmodified' => 0,
            'until'        => 1_900_000_000,
        ]);
    }
    app()->instance(MoodleService::class, $mock);

    return $mock;
}

beforeEach(fn () => config(['encuesta_calidad.plugin_sync_enabled' => true]));

it('mapea el cuestionario íntegro, el curso del aula y la acción/grupo', function () {
    moodleConPaginas([respuestaPlugin()]);

    $this->artisan('encuestas-calidad:sincronizar-moodle')->assertSuccessful();

    $e = EncuestaCalidad::firstOrFail();
    expect($e->forms_id)->toBe('moodle-501')
        ->and($e->origen)->toBe('moodle_plugin')
        ->and($e->alumno_email)->toBe('aaa@example.com')
        ->and($e->numero_accion)->toBe(18)
        ->and($e->numero_grupo)->toBe('21')
        ->and($e->cif_empresa)->toBe('B12345678')
        ->and($e->curso_resuelto)->toBe('Claude Code 40h m')
        ->and($e->curso_tipo)->toBe('moodle')
        ->and($e->curso_origen)->toBe('moodle_plugin')
        ->and($e->fecha_cumplimentacion->format('Y-m-d'))->toBe('2026-09-29')
        ->and($e->moodle_course_id)->toBe(42)
        ->and($e->moodle_timemodified)->toBe(strtotime('2026-09-29 10:05:00'))
        // Escala → columnas de siempre y nuevas
        ->and($e->item_01)->toBe(4)
        ->and($e->item_20)->toBe(2)   // 2.2
        ->and($e->item_21)->toBe(4)   // 4.1 tutores
        ->and($e->item_06)->toBeNull() // 4.1 formadores no contestado
        ->and($e->item_05)->toBeNull() // 3.2 = 9 (NC) → NULL
        ->and($e->satisfaccion_general)->toBe(4)
        // Sí/No
        ->and($e->sino_pruebas_evaluacion)->toBe(1)
        ->and($e->sino_acreditacion)->toBe(2)
        ->and($e->sino_recomendaria)->toBe(1)
        ->and($e->observaciones)->toBe('Muy útil')
        ->and($e->sexo)->toBe('Mujer');
});

it('vincula al alumno por email y no sobrescribe el curso con la deducción por fecha', function () {
    $empresa = Empresa::factory()->create();
    $alumno = Alumno::factory()->create(['email' => 'aaa@example.com', 'empresa_id' => $empresa->id]);
    MoodleMatriculaIndex::create([
        'email' => 'otro@example.com', 'moodle_course_id' => 42, 'curso_fullname' => 'Claude Code 40h m',
        'tutor_label' => 'David Guerra', 'capturado_en' => now(),
    ]);

    moodleConPaginas([respuestaPlugin()]);
    $this->artisan('encuestas-calidad:sincronizar-moodle')->assertSuccessful();

    $e = EncuestaCalidad::firstOrFail();
    expect($e->alumno_id)->toBe($alumno->id)
        ->and($e->curso_resuelto)->toBe('Claude Code 40h m')
        ->and($e->tutor_label)->toBe('David Guerra');
});

it('es idempotente y recorre todas las páginas', function () {
    moodleConPaginas([respuestaPlugin(['id' => 1])], [respuestaPlugin(['id' => 2, 'useremail' => 'b@example.com'])]);
    $this->artisan('encuestas-calidad:sincronizar-moodle')->assertSuccessful();
    expect(EncuestaCalidad::count())->toBe(2);

    // Segunda corrida: el alumno edita su respuesta → se actualiza, no se duplica
    moodleConPaginas([respuestaPlugin(['id' => 1, 'v10' => 2, 'timemodified' => strtotime('2026-09-30 09:00:00')])]);
    $this->artisan('encuestas-calidad:sincronizar-moodle')->assertSuccessful();

    expect(EncuestaCalidad::count())->toBe(2)
        ->and(EncuestaCalidad::where('forms_id', 'moodle-1')->value('satisfaccion_general'))->toBe(2);
});

it('reanuda desde el último timemodified guardado con solape', function () {
    EncuestaCalidad::create(['forms_id' => 'moodle-1', 'origen' => 'moodle_plugin', 'moodle_timemodified' => 1_800_000_000]);

    $mock = Mockery::mock(MoodleService::class);
    $mock->shouldReceive('getCalidadFundaeResponses')->once()
        ->withArgs(fn ($since) => $since === 1_800_000_000 - 60)
        ->andReturn(['responses' => [], 'total' => 0, 'hasmore' => false, 'lastmodified' => 0, 'until' => 0]);
    app()->instance(MoodleService::class, $mock);

    $this->artisan('encuestas-calidad:sincronizar-moodle')->assertSuccessful();
});

it('no hace nada con el interruptor apagado', function () {
    config(['encuesta_calidad.plugin_sync_enabled' => false]);
    $mock = Mockery::mock(MoodleService::class);
    $mock->shouldNotReceive('getCalidadFundaeResponses');
    app()->instance(MoodleService::class, $mock);

    $this->artisan('encuestas-calidad:sincronizar-moodle')->assertSuccessful();
    expect(EncuestaCalidad::count())->toBe(0);
});

it('en dry-run lee pero no guarda', function () {
    config(['encuesta_calidad.plugin_sync_enabled' => false]);
    moodleConPaginas([respuestaPlugin()]);

    $this->artisan('encuestas-calidad:sincronizar-moodle', ['--dry-run' => true])->assertSuccessful();
    expect(EncuestaCalidad::count())->toBe(0);
});

it('guarda la autorización para publicar la reseña con el nombre que eligió el alumno', function (int $forma, string $esperado) {
    moodleConPaginas([respuestaPlugin([
        'userfirstname' => 'ana maría', 'userlastname' => 'garcía lópez',
        'publicarresena' => 1, 'nombrepublico' => $forma,
        'consentimientotime' => strtotime('2026-10-06 10:00:00'), 'consentimientoversion' => '2026-10-06',
    ])]);

    $this->artisan('encuestas-calidad:sincronizar-moodle')->assertSuccessful();

    $e = EncuestaCalidad::firstOrFail();
    expect($e->resena_autorizada)->toBeTrue()
        ->and($e->resena_nombre_publico)->toBe($esperado)
        ->and($e->resena_consentimiento_version)->toBe('2026-10-06')
        ->and($e->resena_consentimiento_en->timestamp)->toBe(strtotime('2026-10-06 10:00:00'));
})->with([
    'nombre completo'   => [1, 'Ana María García López'],
    'nombre e inicial'  => [2, 'Ana María G.'],
    'anónimo'           => [3, 'Anónimo'],
]);

it('sin autorización no guarda nombre público, y si la retira deja de estar autorizada', function () {
    moodleConPaginas([respuestaPlugin()]); // sin campos de consentimiento (versión antigua del plugin)
    $this->artisan('encuestas-calidad:sincronizar-moodle')->assertSuccessful();
    expect(EncuestaCalidad::firstOrFail()->resena_autorizada)->toBeFalse()
        ->and(EncuestaCalidad::firstOrFail()->resena_nombre_publico)->toBeNull();

    moodleConPaginas([respuestaPlugin(['publicarresena' => 1, 'nombrepublico' => 2, 'timemodified' => strtotime('2026-09-30 09:00:00')])]);
    $this->artisan('encuestas-calidad:sincronizar-moodle')->assertSuccessful();
    expect(EncuestaCalidad::firstOrFail()->resena_nombre_publico)->toBe('Alumno A.');

    moodleConPaginas([respuestaPlugin(['publicarresena' => 0, 'nombrepublico' => null, 'timemodified' => strtotime('2026-10-01 09:00:00')])]);
    $this->artisan('encuestas-calidad:sincronizar-moodle')->assertSuccessful();
    expect(EncuestaCalidad::firstOrFail()->resena_autorizada)->toBeFalse()
        ->and(EncuestaCalidad::firstOrFail()->resena_nombre_publico)->toBeNull();
});

it('el listado marca y filtra a quien autoriza publicar su reseña', function () {
    EncuestaCalidad::create(['forms_id' => 'a', 'origen' => 'moodle_plugin', 'fecha_cumplimentacion' => '2026-09-29', 'satisfaccion_general' => 4,
        'alumno_nombre' => 'Con Permiso', 'resena_autorizada' => true, 'resena_nombre_publico' => 'Con P.']);
    EncuestaCalidad::create(['forms_id' => 'b', 'origen' => 'moodle_plugin', 'fecha_cumplimentacion' => '2026-09-29', 'satisfaccion_general' => 4,
        'alumno_nombre' => 'Sin Permiso']);

    \Livewire\Livewire::test(\App\Livewire\Webcurso\EncuestasCalidadIndex::class)
        ->assertSee('Autoriza publicar · Con P.')
        ->set('soloResenaAutorizada', true)
        ->assertSee('Con Permiso')
        ->assertDontSee('Sin Permiso');
});

it('pestaña Reseñas: solo las autorizadas, con su nombre público, comentario y filtro', function () {
    EncuestaCalidad::create(['forms_id' => 'r1', 'origen' => 'moodle_plugin', 'fecha_cumplimentacion' => '2026-09-29', 'satisfaccion_general' => 4,
        'alumno_nombre' => 'Ana García', 'resena_autorizada' => true, 'resena_nombre_publico' => 'Ana G.',
        'observaciones' => 'Curso muy práctico', 'resena_consentimiento_en' => now()]);
    EncuestaCalidad::create(['forms_id' => 'r2', 'origen' => 'moodle_plugin', 'fecha_cumplimentacion' => '2026-09-29', 'satisfaccion_general' => 3,
        'alumno_nombre' => 'Luis Sin Texto', 'resena_autorizada' => true, 'resena_nombre_publico' => 'Anónimo', 'resena_consentimiento_en' => now()]);
    EncuestaCalidad::create(['forms_id' => 'r3', 'origen' => 'moodle_plugin', 'fecha_cumplimentacion' => '2026-09-29', 'satisfaccion_general' => 4,
        'alumno_nombre' => 'No Autoriza', 'observaciones' => 'Comentario privado']);

    \Livewire\Livewire::test(\App\Livewire\Webcurso\EncuestasCalidadIndex::class)
        ->set('pestana', 'resenas')
        ->assertViewHas('nResenas', 2)
        ->assertSee('Ana G.')
        ->assertSee('Curso muy práctico')
        ->assertSee('Luis Sin Texto')
        ->assertDontSee('Comentario privado')
        ->set('filtroResenas', 'con_comentario')
        ->assertSee('Curso muy práctico')
        ->assertDontSee('Luis Sin Texto');
});
