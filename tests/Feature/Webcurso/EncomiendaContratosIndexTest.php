<?php

use App\Livewire\Webcurso\EncomiendaContratosIndex;
use App\Models\EncomiendaAlumnoStaging;
use App\Models\EncomiendaContrato;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renderiza el listado de contratos de encomienda con sus estados', function () {
    EncomiendaContrato::create([
        'source_id'            => 1,
        'referencia_aceptacion' => 'CE-20260101-abcdef',
        'empresa_cif'          => 'B12345678',
        'empresa_razon_social' => 'Empresa Test SL',
        'firmante_nombre'      => 'Juan Firmante',
        'estado_externo'       => 'recibido',
        'estado_procesamiento' => 'pendiente_empresa',
    ]);

    Livewire::test(EncomiendaContratosIndex::class)
        ->assertOk()
        ->assertSee('Empresa Test SL')
        ->assertSee('Pendiente empresa');
});

it('filtra por estado de procesamiento', function () {
    EncomiendaContrato::create(['source_id' => 1, 'empresa_razon_social' => 'Uno SL', 'estado_procesamiento' => 'pendiente_empresa']);
    EncomiendaContrato::create(['source_id' => 2, 'empresa_razon_social' => 'Dos SL', 'estado_procesamiento' => 'candidato_creado']);

    Livewire::test(EncomiendaContratosIndex::class)
        ->set('filtroEstado', 'candidato_creado')
        ->assertSee('Dos SL')
        ->assertDontSee('Uno SL');
});

it('descarta un contrato (soft), lo oculta y oculta sus alumnos pendientes', function () {
    $c = EncomiendaContrato::create([
        'source_id' => 1, 'empresa_razon_social' => 'PruebaBorrar SL', 'estado_procesamiento' => 'pendiente_empresa',
    ]);
    $stg = EncomiendaAlumnoStaging::create([
        'source_id' => 10, 'encomienda_contrato_id' => $c->id, 'contrato_source_id' => 1,
        'nombre' => 'Test', 'estado' => 'pendiente',
    ]);

    Livewire::test(EncomiendaContratosIndex::class)->call('descartar', $c->id);

    expect($c->fresh()->descartado_en)->not->toBeNull();
    expect($stg->fresh()->estado)->toBe('descartado');

    // Oculto por defecto, visible con verDescartados
    Livewire::test(EncomiendaContratosIndex::class)->assertDontSee('PruebaBorrar SL');
    Livewire::test(EncomiendaContratosIndex::class)->set('verDescartados', true)->assertSee('PruebaBorrar SL');
});

it('restaura un contrato descartado y reactiva sus alumnos', function () {
    $c = EncomiendaContrato::create([
        'source_id' => 1, 'empresa_razon_social' => 'PruebaBorrar SL', 'descartado_en' => now(),
    ]);
    $stg = EncomiendaAlumnoStaging::create([
        'source_id' => 10, 'encomienda_contrato_id' => $c->id, 'contrato_source_id' => 1,
        'nombre' => 'Test', 'estado' => 'descartado',
    ]);

    Livewire::test(EncomiendaContratosIndex::class)->call('restaurar', $c->id);

    expect($c->fresh()->descartado_en)->toBeNull();
    expect($stg->fresh()->estado)->toBe('pendiente');
});
