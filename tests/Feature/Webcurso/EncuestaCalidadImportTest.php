<?php

use App\Models\Alumno;
use App\Models\AlumnoLegacyCurso;
use App\Models\Empresa;
use App\Models\EncuestaCalidad;
use App\Models\ParticipanteBonificado;
use App\Services\Webcurso\EncuestaCalidadService;
use Illuminate\Http\UploadedFile;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function archivoMuestra(): UploadedFile
{
    return new UploadedFile(
        base_path('tests/Fixtures/encuestas/muestra.csv'),
        'muestra.csv',
        'text/csv',
        null,
        true // modo test: evita el check is_uploaded_file
    );
}

it('importa las respuestas del cuestionario de calidad desde CSV', function () {
    $r = (new EncuestaCalidadService())->importarDesdeArchivo(archivoMuestra());

    expect($r['procesados'])->toBe(3);
    expect(EncuestaCalidad::count())->toBe(3);

    $e = EncuestaCalidad::where('forms_id', '1001')->first();
    expect($e->satisfaccion_general)->toBe(4);
    expect($e->numero_accion)->toBe(7);
    expect($e->numero_grupo)->toBe('2');
    expect($e->origen)->toBe('import');
    expect($e->fecha_cumplimentacion->format('Y-m-d'))->toBe('2023-01-04'); // M/D/Y americano
    expect($e->alumno_nombre)->toBe('Juan Perez Gomez'); // Title Case
});

it('mapea el 3.2 duplicado del Form a item_05 y item_19', function () {
    (new EncuestaCalidadService())->importarDesdeArchivo(archivoMuestra());

    $e = EncuestaCalidad::where('forms_id', '1001')->first();
    expect($e->item_04)->toBe(4); // 3.1
    expect($e->item_05)->toBe(4); // 3.2 (1ª aparición)
    expect($e->item_19)->toBe(4); // 3.2 (2ª aparición, duplicado)
});

it('conserva las observaciones multilínea entre comillas', function () {
    (new EncuestaCalidadService())->importarDesdeArchivo(archivoMuestra());

    $e = EncuestaCalidad::where('forms_id', '1002')->first();
    expect($e->satisfaccion_general)->toBe(1);
    expect($e->observaciones)->toContain('Falta mas practica');
});

it('vincula al alumno por email', function () {
    $empresa = Empresa::factory()->create();
    $alumno = Alumno::factory()->create(['email' => 'juan@empresa.com', 'empresa_id' => $empresa->id]);

    (new EncuestaCalidadService())->importarDesdeArchivo(archivoMuestra());

    $e = EncuestaCalidad::where('forms_id', '1001')->first();
    expect($e->alumno_id)->toBe($alumno->id);
});

it('vincula al alumno por nombre cuando no hay email (fallback)', function () {
    $empresa = Empresa::factory()->create();
    $alumno = Alumno::factory()->create([
        'nombre' => 'Maria', 'apellido1' => 'Lopez', 'apellido2' => 'Ruiz',
        'email' => null, 'empresa_id' => $empresa->id,
    ]);

    (new EncuestaCalidadService())->importarDesdeArchivo(archivoMuestra());

    $e = EncuestaCalidad::where('forms_id', '1002')->first();
    expect($e->alumno_id)->toBe($alumno->id);
});

it('resuelve el curso por la fecha cuando cae dentro de un curso del alumno', function () {
    $empresa = Empresa::factory()->create();
    $alumno = Alumno::factory()->create(['email' => 'ana@empresa.com', 'nif' => '11111111H', 'empresa_id' => $empresa->id]);
    AlumnoLegacyCurso::create([
        'nif' => '11111111H', 'curso_titulo' => 'Contabilidad Avanzada',
        'fecha_inicio' => '2026-02-01', 'fecha_fin' => '2026-03-01',
    ]);

    $service = new EncuestaCalidadService();
    $service->guardarRespuesta([
        'forms_id' => 'c-1', 'alumno_email' => 'ana@empresa.com',
        'fecha_cumplimentacion' => \Illuminate\Support\Carbon::parse('2026-02-20'),
        'satisfaccion_general' => 4,
    ], 'import');

    $e = EncuestaCalidad::where('forms_id', 'c-1')->first();
    expect($e->curso_tipo)->toBe('legacy');
    expect($e->curso_resuelto)->toBe('Contabilidad Avanzada');
    expect($e->curso_origen)->toBe('fecha_alumno');
});

it('NO resuelve el curso si la fecha cae fuera del rango (solo dentro de fechas)', function () {
    $empresa = Empresa::factory()->create();
    Alumno::factory()->create(['email' => 'ana@empresa.com', 'nif' => '22222222J', 'empresa_id' => $empresa->id]);
    AlumnoLegacyCurso::create([
        'nif' => '22222222J', 'curso_titulo' => 'Curso Enero',
        'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-01-31',
    ]);

    (new EncuestaCalidadService())->guardarRespuesta([
        'forms_id' => 'c-2', 'alumno_email' => 'ana@empresa.com',
        'fecha_cumplimentacion' => \Illuminate\Support\Carbon::parse('2026-02-15'), // 15 días después del fin
        'satisfaccion_general' => 3,
    ], 'import');

    $e = EncuestaCalidad::where('forms_id', 'c-2')->first();
    expect($e->curso_tipo)->toBeNull();
    expect($e->curso_resuelto)->toBeNull();
});

it('desempata por coincidencia de texto de denominación cuando hay varios cursos', function () {
    $empresa = Empresa::factory()->create();
    Alumno::factory()->create(['email' => 'ana@empresa.com', 'nif' => '33333333P', 'empresa_id' => $empresa->id]);
    // Dos cursos que contienen la fecha 20/01; el de fin más lejano es "Marketing Digital"
    AlumnoLegacyCurso::create(['nif' => '33333333P', 'curso_titulo' => 'Excel Basico', 'fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-01-25']);
    AlumnoLegacyCurso::create(['nif' => '33333333P', 'curso_titulo' => 'Marketing Digital', 'fecha_inicio' => '2026-01-10', 'fecha_fin' => '2026-02-28']);

    (new EncuestaCalidadService())->guardarRespuesta([
        'forms_id' => 'c-3', 'alumno_email' => 'ana@empresa.com',
        'denominacion_accion' => 'Marketing Digital 40h',
        'fecha_cumplimentacion' => \Illuminate\Support\Carbon::parse('2026-01-20'),
        'satisfaccion_general' => 4,
    ], 'import');

    $e = EncuestaCalidad::where('forms_id', 'c-3')->first();
    expect($e->curso_resuelto)->toBe('Marketing Digital'); // gana por texto pese a no ser el fin más cercano
});

it('ante un bonificado sin nombre y un legacy con nombre (mismo curso), elige el que tiene nombre', function () {
    $empresa = Empresa::factory()->create();
    Alumno::factory()->create(['email' => 'laura@empresa.com', 'nif' => '77559843W', 'empresa_id' => $empresa->id]);
    // Mismo curso real en dos fuentes, mismas fechas; el bonificado no tiene nombre
    ParticipanteBonificado::create([
        'nif_participante' => '77559843W', 'nombre' => 'Laura',
        'fecha_inicio' => '2026-03-23', 'fecha_fin' => '2026-05-22',
    ]);
    AlumnoLegacyCurso::create([
        'nif' => '77559843W', 'curso_titulo' => 'Wordpress Total + IA',
        'fecha_inicio' => '2026-03-23', 'fecha_fin' => '2026-05-22',
    ]);

    (new EncuestaCalidadService())->guardarRespuesta([
        'forms_id' => 'c-laura', 'alumno_email' => 'laura@empresa.com',
        'fecha_cumplimentacion' => \Illuminate\Support\Carbon::parse('2026-05-15'),
        'satisfaccion_general' => 4,
    ], 'import');

    $e = EncuestaCalidad::where('forms_id', 'c-laura')->first();
    expect($e->curso_tipo)->toBe('legacy');
    expect($e->curso_resuelto)->toBe('Wordpress Total + IA');
});

it('encuentra el curso aunque esté en otra ficha del alumno (email compartido, NIF distinto)', function () {
    $empresa = Empresa::factory()->create();
    // Misma persona, dos fichas con NIF distinto (divergencia FUNDAE vs legacy)
    $fundae = Alumno::factory()->create(['nombre' => 'Juan', 'apellido1' => 'Sanchez', 'nif' => '74848858G', 'empresa_id' => $empresa->id]);
    $legacy = Alumno::factory()->create(['nombre' => 'Juan', 'apellido1' => 'Sanchez', 'nif' => '44818858J', 'empresa_id' => $empresa->id]);
    // El curso está bajo el NIF legacy (ficha $legacy)
    AlumnoLegacyCurso::create([
        'nif' => '44818858J', 'curso_titulo' => 'ChatGPT para Administración',
        'fecha_inicio' => '2026-01-23', 'fecha_fin' => '2026-03-24',
    ]);

    // La encuesta se enlazó a la ficha FUNDAE; el curso debe salir de la gemela
    $cand = (new EncuestaCalidadService())->resolverCursoPorFecha(
        [$fundae->id, $legacy->id],
        \Illuminate\Support\Carbon::parse('2026-03-19'),
        null
    );

    expect($cand['curso_resuelto'])->toBe('ChatGPT para Administración');
    expect($cand['curso_tipo'])->toBe('legacy');
});

it('es idempotente: reimportar no duplica (UPSERT por forms_id)', function () {
    $service = new EncuestaCalidadService();
    $service->importarDesdeArchivo(archivoMuestra());
    $service->importarDesdeArchivo(archivoMuestra());

    expect(EncuestaCalidad::count())->toBe(3);
});
