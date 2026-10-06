<?php

use App\Livewire\Webcurso\CursosSiguientesIndex;
use App\Livewire\Webcurso\EncuestasCalidadIndex;
use App\Models\Alumno;
use App\Models\AlumnoLegacyCurso;
use App\Models\CursoSiguiente;
use App\Models\EncuestaCalidad;
use App\Models\MoodleCategoria;
use App\Models\MoodleCurso;
use App\Services\Webcurso\CursoSiguienteService;
use Livewire\Livewire;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function cursoCatalogo(string $titulo, int $horas = 40, string $categoria = 'IA'): MoodleCurso
{
    $cat = MoodleCategoria::firstOrCreate(['nombre' => $categoria]);

    return MoodleCurso::create(['moodle_categoria_id' => $cat->id, 'titulo' => $titulo, 'precio' => 100, 'horas' => $horas]);
}

function encuestaCurso(string $curso, array $attrs = []): EncuestaCalidad
{
    return EncuestaCalidad::create(array_merge([
        'forms_id'              => 'f-' . uniqid('', true),
        'origen'                => 'moodle_plugin',
        'fecha_cumplimentacion' => '2026-09-29',
        'alumno_nombre'         => 'Alumno Aaa',
        'alumno_email'          => 'aaa@example.com',
        'curso_resuelto'        => $curso,
        'satisfaccion_general'  => 4,
        'sino_recomendaria'     => 1,
    ], $attrs));
}

function siguienteActivo(string $origen, MoodleCurso $destino): CursoSiguiente
{
    return CursoSiguiente::create([
        'curso_origen_clave' => CursoSiguiente::claveCurso($origen), 'curso_origen_nombre' => $origen,
        'moodle_curso_id' => $destino->id, 'activo' => true,
    ]);
}

it('normaliza el nombre del curso entre fuentes', function () {
    expect(CursoSiguiente::claveCurso('Claude Code 40h m'))->toBe('claude code')
        ->and(CursoSiguiente::claveCurso('Curso de Claude Code (REPASO)'))->toBe('claude code')
        ->and(CursoSiguiente::claveCurso('ChatGPT Nivel 2 60h a'))->toBe('chatgpt nivel 2')
        ->and(CursoSiguiente::claveCurso('Excel Básico'))->toBe('excel basico')
        ->and(CursoSiguiente::claveCurso('ChatGPT en Excel 30 horas Prof. David Guerra'))->toBe('chatgpt en excel')
        ->and(CursoSiguiente::claveCurso('Chatgpt en excel 30hb m'))->toBe('chatgpt en excel');
});

it('propone el siguiente nivel del catálogo y lo deja pendiente de confirmar', function () {
    $avanzado = cursoCatalogo('Claude Code Avanzado');
    $nivel2 = cursoCatalogo('ChatGPT Nivel 2');
    cursoCatalogo('ChatGPT Nivel 3');
    $intermedio = cursoCatalogo('Excel Intermedio');
    cursoCatalogo('Excel Avanzado');

    encuestaCurso('Claude Code 40h m');
    encuestaCurso('ChatGPT Nivel 1 60h m');
    encuestaCurso('Excel básico');
    encuestaCurso('Canva');

    expect((new CursoSiguienteService())->sugerir())->toBe(3);

    $destino = fn ($curso) => CursoSiguiente::where('curso_origen_clave', CursoSiguiente::claveCurso($curso))->value('moodle_curso_id');
    expect($destino('Claude Code'))->toBe($avanzado->id)
        ->and($destino('ChatGPT Nivel 1'))->toBe($nivel2->id)
        ->and($destino('Excel básico'))->toBe($intermedio->id)
        ->and($destino('Canva'))->toBeNull()
        ->and(CursoSiguiente::pendientesDeConfirmar()->count())->toBe(3);

    // Repetir no duplica
    expect((new CursoSiguienteService())->sugerir())->toBe(0);
});

it('usa la categoría con nivel del catálogo (ChatGPT Nivel 1 → cursos de Nivel 2)', function () {
    cursoCatalogo('ChatGPT Inicial', 50, 'ChatGPT Nivel 1');
    $rrhh = cursoCatalogo('ChatGPT para RRHH', 50, 'ChatGPT Nivel 2');
    $mkt = cursoCatalogo('ChatGPT para Marketing Online', 50, 'ChatGPT Nivel 2');
    cursoCatalogo('ChatGPT en Excel', 30, 'ChatGPT Nivel 3');

    // Copia del aula de un tutor: el sufijo "Prof." y las horas no impiden casar
    encuestaCurso('ChatGPT Inicial 50 horas Prof. David Guerra');

    expect((new CursoSiguienteService())->sugerir())->toBe(2)
        ->and(CursoSiguiente::where('curso_origen_clave', 'chatgpt inicial')->pluck('moodle_curso_id')->sort()->values()->all())
        ->toBe(collect([$rrhh->id, $mkt->id])->sort()->values()->all());
});

it('lista como oportunidad al promotor y excluye detractores, no recomendadores y sin siguiente', function () {
    siguienteActivo('Claude Code', cursoCatalogo('Claude Code Avanzado'));

    encuestaCurso('Claude Code 40h m', ['alumno_email' => 'promotor@example.com', 'alumno_nombre' => 'Promotor']);
    encuestaCurso('Claude Code 40h m', ['alumno_email' => 'nota3@example.com', 'satisfaccion_general' => 3]);
    encuestaCurso('Claude Code 40h m', ['alumno_email' => 'norecomienda@example.com', 'sino_recomendaria' => 2]);
    encuestaCurso('Claude Code 40h m', ['alumno_email' => 'form@example.com', 'origen' => 'import', 'sino_recomendaria' => null]);
    encuestaCurso('Canva', ['alumno_email' => 'canva@example.com']);

    $emails = (new CursoSiguienteService())->oportunidades(EncuestaCalidad::query())
        ->map(fn ($o) => $o['encuesta']->alumno_email)->sort()->values()->all();

    expect($emails)->toBe(['form@example.com', 'promotor@example.com']);
});

it('excluye al alumno que ya hizo el curso siguiente', function () {
    siguienteActivo('Claude Code', cursoCatalogo('Claude Code Avanzado'));

    // Ya hizo el avanzado según otra encuesta…
    encuestaCurso('Claude Code 40h m', ['alumno_email' => 'ya@example.com']);
    encuestaCurso('Claude Code Avanzado 60h m', ['alumno_email' => 'ya@example.com']);
    // …o según su historial legacy
    $alumno = Alumno::factory()->create(['email' => 'legacy@example.com']);
    AlumnoLegacyCurso::create(['nif' => $alumno->nif, 'source_mc_id' => 1, 'curso_titulo' => 'Claude Code avanzado', 'fecha_inicio' => '2025-01-01', 'fecha_fin' => '2025-02-01']);
    encuestaCurso('Claude Code 40h m', ['alumno_email' => 'legacy@example.com', 'alumno_id' => $alumno->id]);
    // Este no lo ha hecho
    encuestaCurso('Claude Code 40h m', ['alumno_email' => 'nuevo@example.com']);

    $emails = (new CursoSiguienteService())->oportunidades(EncuestaCalidad::query())
        ->map(fn ($o) => $o['encuesta']->alumno_email)->all();

    expect($emails)->toBe(['nuevo@example.com']);
});

it('las propuestas sin confirmar no generan oportunidades', function () {
    CursoSiguiente::create([
        'curso_origen_clave' => 'claude code', 'curso_origen_nombre' => 'Claude Code',
        'moodle_curso_id' => cursoCatalogo('Claude Code Avanzado')->id, 'activo' => false, 'sugerido_auto' => true,
    ]);
    encuestaCurso('Claude Code 40h m');

    expect((new CursoSiguienteService())->oportunidades(EncuestaCalidad::query()))->toHaveCount(0);
});

it('pestaña oportunidades: muestra la sugerencia y guarda el seguimiento', function () {
    siguienteActivo('Claude Code', cursoCatalogo('Claude Code Avanzado'));
    $e = encuestaCurso('Claude Code 40h m', ['alumno_nombre' => 'Promotor Aula']);

    Livewire::test(EncuestasCalidadIndex::class)
        ->set('pestana', 'oportunidades')
        ->assertSee('Promotor Aula')
        ->assertSee('Claude Code Avanzado')
        ->call('cambiarEstadoOportunidad', $e->id, 'contactado')
        ->call('guardarNotaOportunidad', $e->id, 'Llamar en enero');

    $e->refresh();
    expect($e->oportunidad_estado)->toBe('contactado')
        ->and($e->oportunidad_nota)->toBe('Llamar en enero');

    // "No interesado" la saca de las abiertas
    Livewire::test(EncuestasCalidadIndex::class)
        ->set('pestana', 'oportunidades')
        ->call('cambiarEstadoOportunidad', $e->id, 'no_interesado')
        ->assertDontSee('Promotor Aula');
});

it('pestaña cuestionario completo: medias por pregunta y Sí/No solo del aula', function () {
    encuestaCurso('Claude Code', ['item_20' => 4, 'sino_acreditacion' => 1]);
    encuestaCurso('Claude Code', ['item_20' => 2, 'sino_acreditacion' => 2, 'alumno_email' => 'b@example.com']);
    encuestaCurso('Claude Code', ['origen' => 'import', 'item_20' => 1, 'alumno_email' => 'c@example.com']);

    Livewire::test(EncuestasCalidadIndex::class)
        ->set('pestana', 'cuestionario')
        ->assertSee('2 encuestas del aula')
        ->assertSee('3.00')   // media 2.2 = (4+2)/2, la del Form no cuenta
        ->assertSee('50% Sí');
});

it('pantalla de cursos siguientes: añadir destino y confirmar propuesta', function () {
    $avanzado = cursoCatalogo('Claude Code Avanzado');
    encuestaCurso('Claude Code 40h m');

    Livewire::test(CursosSiguientesIndex::class)
        ->assertSee('Claude Code 40h m')
        ->call('editar', 'claude code')
        ->set('buscarCatalogo', 'Avanz')
        ->assertSee('Claude Code Avanzado')
        ->call('anadirDestino', 'web', $avanzado->id)
        ->call('guardarUrl', $avanzado->id, 'https://www.webcurso.es/courses/claude-code-avanzado');

    expect(CursoSiguiente::activos()->where('curso_origen_clave', 'claude code')->count())->toBe(1)
        ->and($avanzado->fresh()->url)->toBe('https://www.webcurso.es/courses/claude-code-avanzado');
});

it('oportunidades: muestra el saldo de la empresa y si cubre el precio del curso', function () {
    $empresa = \App\Models\Empresa::factory()->create(['credito_disponible' => 500]);
    $alumno = Alumno::factory()->create(['email' => 'saldo@example.com', 'empresa_id' => $empresa->id]);
    $barato = cursoCatalogo('Claude Code Avanzado');           // precio 100
    $caro = MoodleCurso::create(['moodle_categoria_id' => $barato->moodle_categoria_id, 'titulo' => 'Claude Code Experto', 'precio' => 900, 'horas' => 80]);
    siguienteActivo('Claude Code', $barato);
    siguienteActivo('Claude Code', $caro);
    encuestaCurso('Claude Code 40h m', ['alumno_email' => 'saldo@example.com', 'alumno_id' => $alumno->id]);
    // Sin empresa registrada (particular): saldo desconocido
    encuestaCurso('Claude Code 40h m', ['alumno_email' => 'particular@example.com', 'alumno_nombre' => 'Particular Sin Empresa']);

    $ops = (new CursoSiguienteService())->oportunidades(EncuestaCalidad::query())->keyBy(fn ($o) => $o['encuesta']->alumno_email);
    expect($ops['saldo@example.com']['saldo'])->toBe(500.0)
        ->and($ops['saldo@example.com']['cubre'])->toBe(['w:' . $barato->id => true, 'w:' . $caro->id => false])
        ->and($ops['particular@example.com']['saldo'])->toBeNull();

    Livewire::test(EncuestasCalidadIndex::class)
        ->set('pestana', 'oportunidades')
        ->assertSee('500,00 €')
        ->assertSee('lo cubre el saldo')
        ->assertSee('saldo insuficiente')
        ->set('filtroSaldo', 'cubre')
        ->assertDontSee('Particular Sin Empresa');
});

it('cuestionario completo: lista las observaciones de los alumnos del aula', function () {
    encuestaCurso('Claude Code', ['observaciones' => 'Me encantó el módulo de agentes', 'alumno_nombre' => 'Alumno Aula']);
    encuestaCurso('Claude Code', ['origen' => 'import', 'observaciones' => 'Comentario del Form antiguo', 'alumno_email' => 'f@example.com']);

    Livewire::test(EncuestasCalidadIndex::class)
        ->set('pestana', 'cuestionario')
        ->assertSee('Observaciones de los alumnos')
        ->assertSee('Me encantó el módulo de agentes')
        ->assertDontSee('Comentario del Form antiguo');
});

it('resumen: identifica a quien rellenó el cuestionario del aula y permite filtrarlos', function () {
    encuestaCurso('Claude Code', ['alumno_nombre' => 'Alumno Del Aula']);
    encuestaCurso('Claude Code', ['origen' => 'import', 'alumno_nombre' => 'Alumno Del Form', 'alumno_email' => 'f@example.com']);

    Livewire::test(EncuestasCalidadIndex::class)
        ->assertSee('Cuestionario del aula')
        ->assertSee('Form antiguo')
        ->set('filtroOrigen', 'aula')
        ->assertSee('Alumno Del Aula')
        ->assertDontSee('Alumno Del Form');
});

it('propone y ofrece acciones formativas FUNDAE (Claude Code → Claude Code Avanzado) con coste estimado', function () {
    \App\Models\AccionFormativa::factory()->create(['numero_accion' => 248, 'denominacion' => 'Claude Code', 'horas' => 70, 'estado' => 'Alta']);
    $avanzada = \App\Models\AccionFormativa::factory()->create(['numero_accion' => 257, 'denominacion' => 'Claude Code Avanzado Agentes para automatizar', 'horas' => 70, 'estado' => 'Alta']);
    $empresa = \App\Models\Empresa::factory()->create(['credito_disponible' => 1000]);
    $alumno = Alumno::factory()->create(['email' => 'cc@example.com', 'empresa_id' => $empresa->id]);
    encuestaCurso('Claude Code 70h m', ['alumno_email' => 'cc@example.com', 'alumno_id' => $alumno->id]);

    expect((new CursoSiguienteService())->sugerir())->toBe(1);
    $pareja = CursoSiguiente::firstOrFail();
    expect($pareja->accion_formativa_id)->toBe($avanzada->id)
        ->and($pareja->moodle_curso_id)->toBeNull()
        ->and($pareja->destino_titulo)->toBe('Claude Code Avanzado Agentes para automatizar');

    $pareja->update(['activo' => true]);
    $o = (new CursoSiguienteService())->oportunidades(EncuestaCalidad::query())->first();
    $d = $o['destinos']->first();

    // Sin precio en la web: 70 h × 7 €/h (módulo FUNDAE teleformación) = 490 €
    expect($d->tipo)->toBe('accion')
        ->and($d->numero_accion)->toBe(257)
        ->and($d->precio)->toBe(490.0)
        ->and($d->precio_estimado)->toBeTrue()
        ->and($o['cubre'][$d->id])->toBeTrue();

    Livewire::test(EncuestasCalidadIndex::class)
        ->set('pestana', 'oportunidades')
        ->assertSee('AF 257')
        ->assertSee('≈ 490 €');
});

it('la acción formativa toma enlace y precio de su ficha web cuando existe', function () {
    $accion = \App\Models\AccionFormativa::factory()->create(['numero_accion' => 218, 'denominacion' => 'Chatgpt para rrhh 80h m', 'horas' => 80, 'estado' => 'Alta']);
    $web = cursoCatalogo('ChatGPT para RRHH');
    $web->update(['precio' => 660, 'url' => 'https://www.webcurso.es/courses/chatgpt-rrhh']);
    $s = CursoSiguiente::create(['curso_origen_clave' => 'chatgpt inicial', 'curso_origen_nombre' => 'ChatGPT Inicial', 'accion_formativa_id' => $accion->id, 'activo' => true]);

    $svc = new CursoSiguienteService();
    $d = $svc->destino($s->load('accionFormativa'), $svc->webPorClave());

    expect($d->precio)->toBe(660.0)
        ->and($d->precio_estimado)->toBeFalse()
        ->and($d->url)->toBe('https://www.webcurso.es/courses/chatgpt-rrhh');
});
