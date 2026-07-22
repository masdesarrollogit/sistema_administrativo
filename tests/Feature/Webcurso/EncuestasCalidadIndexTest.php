<?php

use App\Livewire\Webcurso\EncuestasCalidadIndex;
use App\Models\Alumno;
use App\Models\AlumnoLegacyCurso;
use App\Models\Empresa;
use App\Models\EncuestaCalidad;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function crearEncuesta(array $attrs = []): EncuestaCalidad
{
    return EncuestaCalidad::create(array_merge([
        'forms_id'              => 'f-' . uniqid(),
        'origen'                => 'import',
        'fecha_cumplimentacion' => '2026-03-01',
        'satisfaccion_general'  => 4,
    ], $attrs));
}

it('renderiza la pantalla de encuestas de calidad', function () {
    crearEncuesta(['alumno_nombre' => 'Promotor Uno', 'satisfaccion_general' => 4]);

    Livewire::test(EncuestasCalidadIndex::class)
        ->assertOk()
        ->assertSee('Encuestas de Calidad')
        ->assertSee('Promotor Uno');
});

it('el filtro "solo 4" muestra promotores y oculta detractores', function () {
    crearEncuesta(['alumno_nombre' => 'Promotor Uno', 'satisfaccion_general' => 4]);
    crearEncuesta(['alumno_nombre' => 'Detractor Dos', 'satisfaccion_general' => 1, 'observaciones' => 'mal curso']);

    Livewire::test(EncuestasCalidadIndex::class)
        ->set('filtroSatisfaccion', '4')
        ->assertSee('Promotor Uno')
        ->assertDontSee('Detractor Dos');
});

it('verDetractores muestra los de nota menor a 3 con su queja', function () {
    crearEncuesta(['alumno_nombre' => 'Promotor Uno', 'satisfaccion_general' => 4]);
    crearEncuesta(['alumno_nombre' => 'Detractor Dos', 'satisfaccion_general' => 1, 'observaciones' => 'mal curso']);

    Livewire::test(EncuestasCalidadIndex::class)
        ->call('verDetractores')
        ->assertSet('filtroSatisfaccion', 'menos3')
        ->assertSee('Detractor Dos')
        ->assertSee('mal curso')
        ->assertDontSee('Promotor Uno');
});

it('abre el historial de cursos del alumno desde el nombre', function () {
    $empresa = Empresa::factory()->create();
    $alumno = Alumno::factory()->create(['nif' => '55555555K', 'empresa_id' => $empresa->id]);
    AlumnoLegacyCurso::create([
        'nif' => '55555555K', 'curso_titulo' => 'Curso de Prueba',
        'fecha_inicio' => '2026-02-01', 'fecha_fin' => '2026-03-01',
    ]);
    crearEncuesta(['alumno_nombre' => 'Persona Uno', 'alumno_id' => $alumno->id]);

    Livewire::test(EncuestasCalidadIndex::class)
        ->call('verHistorial', $alumno->id)
        ->assertSet('mostrarHistorial', true)
        ->assertSee('Historial de cursos')
        ->assertSee('Curso de Prueba');
});

it('la tabla por curso agrupa por curso resuelto, calcula media y honra el mínimo de 3', function () {
    foreach ([4, 4, 4] as $s) { crearEncuesta(['curso_resuelto' => 'Curso A', 'satisfaccion_general' => $s]); }
    foreach ([1, 1, 1] as $s) { crearEncuesta(['curso_resuelto' => 'Curso B', 'satisfaccion_general' => $s]); }
    foreach ([2, 2] as $s)    { crearEncuesta(['curso_resuelto' => 'Curso C', 'satisfaccion_general' => $s]); } // solo 2 → excluido

    Livewire::test(EncuestasCalidadIndex::class)
        // Orden por defecto media desc → Curso A primero; Curso C excluido (minRespuestas)
        ->assertViewHas('porCurso', function ($r) {
            return $r[0]['curso_resuelto'] === 'Curso A'
                && (int) $r[0]['n4'] === 3
                && collect($r)->pluck('curso_resuelto')->doesntContain('Curso C');
        })
        ->set('ordenCurso', 'media_asc')
        ->assertViewHas('porCurso', fn ($r) => $r[0]['curso_resuelto'] === 'Curso B')
        ->set('minRespuestas', false)
        ->assertViewHas('porCurso', fn ($r) => collect($r)->pluck('curso_resuelto')->contains('Curso C'));
});

it('el filtro de curso busca por curso_resuelto y verCurso lo activa', function () {
    crearEncuesta(['curso_resuelto' => 'Alfa Excel', 'alumno_nombre' => 'Ana', 'satisfaccion_general' => 4]);
    crearEncuesta(['curso_resuelto' => 'Beta Word', 'alumno_nombre' => 'Beto', 'satisfaccion_general' => 3]);

    Livewire::test(EncuestasCalidadIndex::class)
        ->set('minRespuestas', false) // cada curso tiene 1 respuesta en este test
        ->set('filtroCurso', 'Alfa')
        ->assertViewHas('porCurso', fn ($r) => count($r) === 1 && $r[0]['curso_resuelto'] === 'Alfa Excel')
        ->assertSee('Ana')
        ->assertDontSee('Beto')
        // verCurso desde la tabla
        ->call('verCurso', 'Beta Word')
        ->assertSet('filtroCurso', 'Beta Word')
        ->assertSee('Beto')
        ->assertDontSee('Ana');
});

it('calcula la distribución global 1-4 con porcentajes', function () {
    crearEncuesta(['satisfaccion_general' => 4]);
    crearEncuesta(['satisfaccion_general' => 4]);
    crearEncuesta(['satisfaccion_general' => 2]);
    crearEncuesta(['satisfaccion_general' => 1]);

    Livewire::test(EncuestasCalidadIndex::class)
        ->assertViewHas('distribucion', function ($d) {
            return $d['total'] === 4 && $d[4]['n'] === 2 && $d[4]['pct'] === 50.0 && $d[1]['n'] === 1;
        });
});

it('calcula las medias por bloque FUNDAE sin truncar los decimales', function () {
    // Bloque "organizacion" = item_01 + item_02. Medias: item_01=(4+3)/2=3.5, item_02=(4+4)/2=4 → bloque=3.75
    crearEncuesta(['satisfaccion_general' => 4, 'item_01' => 4, 'item_02' => 4]);
    crearEncuesta(['satisfaccion_general' => 3, 'item_01' => 3, 'item_02' => 4]);

    Livewire::test(EncuestasCalidadIndex::class)
        ->assertViewHas('porBloque', function ($b) {
            $org = collect($b)->firstWhere('label', 'Organización');
            return $org && abs($org['media'] - 3.75) < 0.01; // no truncado a 3
        });
});

it('calcula la media por tutor (cursos con tutor asignado, ≥3)', function () {
    $tutor = \App\Models\Tutor::factory()->create(['nombre' => 'Raquel', 'apellido1' => 'García']);
    foreach ([4, 4, 3] as $s) { crearEncuesta(['tutor_id' => $tutor->id, 'satisfaccion_general' => $s]); }

    Livewire::test(EncuestasCalidadIndex::class)
        ->assertViewHas('porTutor', fn ($r) => count($r) === 1 && str_contains($r[0]['tutor'], 'Raquel') && $r[0]['respuestas'] === 3);
});

it('exporta el listado filtrado a Excel', function () {
    crearEncuesta(['alumno_nombre' => 'Persona Uno', 'satisfaccion_general' => 4, 'curso_resuelto' => 'Curso X']);

    Livewire::test(EncuestasCalidadIndex::class)
        ->call('exportar')
        ->assertFileDownloaded();
});

it('calcula KPIs de satisfacción respetando el año', function () {
    crearEncuesta(['satisfaccion_general' => 4]);
    crearEncuesta(['satisfaccion_general' => 4]);
    crearEncuesta(['satisfaccion_general' => 1]);
    crearEncuesta(['satisfaccion_general' => 2]);
    crearEncuesta(['satisfaccion_general' => 3, 'fecha_cumplimentacion' => '2023-01-01']); // otro año

    Livewire::test(EncuestasCalidadIndex::class)
        ->assertViewHas('stats', function ($stats) {
            return $stats['n4'] === 2 && $stats['nMenos3'] === 2 && $stats['total'] === 4;
        });
});
