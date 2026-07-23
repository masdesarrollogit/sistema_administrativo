<?php

use App\Models\MoodleMatriculaIndex;
use App\Services\Webcurso\EncuestaCalidadService;
use Illuminate\Support\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/** Helper: inserta una fila en el índice de matrículas de Moodle. */
function indice(string $email, string $fullname, ?string $ini, ?string $fin, int $courseId = 1, ?string $ultimoAcceso = null, ?string $tutorLabel = null): void
{
    MoodleMatriculaIndex::create([
        'email'            => $email,
        'moodle_course_id' => $courseId,
        'curso_fullname'   => $fullname,
        'categoria_id'     => 5,
        'curso_startdate'  => $ini,
        'curso_enddate'    => $fin,
        'ultimo_acceso'    => $ultimoAcceso,
        'tutor_label'      => $tutorLabel,
        'capturado_en'     => now(),
    ]);
}

it('resuelve el curso cuando el email tiene una sola matrícula en el índice', function () {
    indice('ana@example.com', 'Excel Avanzado 60h', '2026-01-10', '2026-02-10');

    $r = (new EncuestaCalidadService())->resolverCursoDesdeIndiceMoodle(
        'ana@example.com', Carbon::parse('2026-05-01'), null
    );

    expect($r)->not->toBeNull();
    expect($r['curso_resuelto'])->toBe('Excel Avanzado 60h');
    expect($r['curso_tipo'])->toBe('moodle');
    expect($r['curso_origen'])->toBe('moodle_api');
});

it('elige por ventana de fecha cuando hay varias matrículas', function () {
    indice('luis@example.com', 'Curso Enero', '2026-01-01', '2026-01-31', 1);
    indice('luis@example.com', 'Curso Junio', '2026-06-01', '2026-06-30', 2);

    $r = (new EncuestaCalidadService())->resolverCursoDesdeIndiceMoodle(
        'luis@example.com', Carbon::parse('2026-06-15'), null
    );

    expect($r['curso_resuelto'])->toBe('Curso Junio');
});

it('desempata por coincidencia de texto cuando dos ventanas contienen la fecha', function () {
    indice('eva@example.com', 'Marketing Digital 40h', '2026-03-01', '2026-07-31', 1);
    indice('eva@example.com', 'Contabilidad Basica 30h', '2026-03-01', '2026-07-31', 2);

    $r = (new EncuestaCalidadService())->resolverCursoDesdeIndiceMoodle(
        'eva@example.com', Carbon::parse('2026-05-01'), 'Marketing Digital'
    );

    expect($r['curso_resuelto'])->toBe('Marketing Digital 40h');
});

it('devuelve null si el email no está en el índice (marca sin_match aguas arriba)', function () {
    indice('otro@example.com', 'Excel 60h', '2026-01-01', '2026-02-01');

    $r = (new EncuestaCalidadService())->resolverCursoDesdeIndiceMoodle(
        'noexiste@example.com', Carbon::parse('2026-05-01'), null
    );

    expect($r)->toBeNull();
});

it('es ambiguo (null) si hay varias matrículas y ninguna encaja por fecha ni por texto', function () {
    indice('multi@example.com', 'Curso A', '2020-01-01', '2020-02-01', 1);
    indice('multi@example.com', 'Curso B', '2021-01-01', '2021-02-01', 2);

    $r = (new EncuestaCalidadService())->resolverCursoDesdeIndiceMoodle(
        'multi@example.com', Carbon::parse('2026-05-01'), null
    );

    expect($r)->toBeNull();
});

it('prioriza el último acceso cercano a la fecha por encima de la ventana del curso', function () {
    // Curso "contenedor" con ventana amplísima (2011 → sin fin) que engañaría al
    // criterio de ventana, pero el alumno no entró cerca de la encuesta.
    indice('juan@example.com', 'Curso Contenedor Viejo', '2011-01-01', null, 1, '2019-01-01');
    // Curso real: su ventana NO contiene la fecha, pero el último acceso sí está al lado.
    indice('juan@example.com', 'Canva con IA 80h', '2024-10-01', '2024-11-30', 2, '2024-12-28');

    $r = (new EncuestaCalidadService())->resolverCursoDesdeIndiceMoodle(
        'juan@example.com', Carbon::parse('2024-12-29'), null
    );

    expect($r['curso_resuelto'])->toBe('Canva con IA 80h');
});

it('devuelve la etiqueta de tutor del curso elegido', function () {
    indice('pepe@example.com', 'Excel 40h Prof. David Guerra', '2026-01-01', '2026-01-31', 1, '2026-01-30', 'David Guerra');

    $r = (new EncuestaCalidadService())->resolverCursoDesdeIndiceMoodle(
        'pepe@example.com', Carbon::parse('2026-02-01'), null
    );

    expect($r['tutor_label'])->toBe('David Guerra');
});

it('etiquetaTutorDesdeIndice cruza email + nombre de curso exacto', function () {
    indice('rosa@example.com', 'ChatGPT Inicial 50 horas', '2026-03-01', '2026-03-31', 2, null, 'Álvaro Pino / Raquel García');

    $label = (new EncuestaCalidadService())->etiquetaTutorDesdeIndice('rosa@example.com', 'ChatGPT Inicial 50 horas');
    expect($label)->toBe('Álvaro Pino / Raquel García');

    // Nombre que no existe en el índice → null
    expect((new EncuestaCalidadService())->etiquetaTutorDesdeIndice('rosa@example.com', 'Otro curso'))->toBeNull();
});

it('respeta el margen de días al comparar con la ventana del curso', function () {
    // Encuesta rellenada 5 días después de terminar el curso; con margen 15 debe casar.
    indice('borde@example.com', 'Power BI 40h', '2026-04-01', '2026-04-30');

    $r = (new EncuestaCalidadService())->resolverCursoDesdeIndiceMoodle(
        'borde@example.com', Carbon::parse('2026-05-05'), null
    );

    expect($r)->not->toBeNull();
    expect($r['curso_resuelto'])->toBe('Power BI 40h');
});
