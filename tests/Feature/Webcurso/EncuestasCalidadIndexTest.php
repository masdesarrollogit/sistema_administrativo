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

it('los rankings agrupan por curso resuelto y exigen mínimo 3 respuestas', function () {
    // Curso A: media 4 (3 respuestas) · Curso B: media 1 (3 respuestas)
    foreach ([4, 4, 4] as $s) { crearEncuesta(['curso_resuelto' => 'Curso A', 'satisfaccion_general' => $s]); }
    foreach ([1, 1, 1] as $s) { crearEncuesta(['curso_resuelto' => 'Curso B', 'satisfaccion_general' => $s]); }
    // Curso C: solo 2 respuestas → excluido del ranking
    foreach ([2, 2] as $s) { crearEncuesta(['curso_resuelto' => 'Curso C', 'satisfaccion_general' => $s]); }

    Livewire::test(EncuestasCalidadIndex::class)
        ->assertViewHas('mejorValorados', fn ($r) => $r[0]['curso_resuelto'] === 'Curso A')
        ->assertViewHas('peorValorados', fn ($r) => $r[0]['curso_resuelto'] === 'Curso B')
        ->assertViewHas('mejorValorados', fn ($r) => collect($r)->pluck('curso_resuelto')->doesntContain('Curso C'));
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
