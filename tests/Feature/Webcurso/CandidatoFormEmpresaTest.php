<?php

use App\Livewire\Webcurso\CandidatoForm;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\TipoCandidato;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function tipoOrganizadora(): TipoCandidato
{
    return TipoCandidato::factory()->create(['codigo' => 'empresa_organizadora']);
}

it('rellena la razón social al escribir un CIF de empresa existente', function () {
    $tipo = tipoOrganizadora();
    Empresa::factory()->create(['cif' => 'B12345678', 'razon_social' => 'Acme SL']);

    Livewire::test(CandidatoForm::class)
        ->set('tipo_candidato_id', $tipo->id)
        ->set('cif_empresa', 'B12345678')
        ->assertSet('empresaEncontrada', true)
        ->assertSet('razon_social_empresa', 'Acme SL');
});

it('encuentra la empresa aunque el CIF venga con guiones o minúsculas', function () {
    $tipo = tipoOrganizadora();
    Empresa::factory()->create(['cif' => 'B12345678', 'razon_social' => 'Acme SL']);

    Livewire::test(CandidatoForm::class)
        ->set('tipo_candidato_id', $tipo->id)
        ->set('cif_empresa', 'b-12345678')
        ->assertSet('empresaEncontrada', true)
        ->assertSet('razon_social_empresa', 'Acme SL');
});

it('marca no encontrada y deja vacía la razón social con un CIF inexistente', function () {
    $tipo = tipoOrganizadora();

    Livewire::test(CandidatoForm::class)
        ->set('tipo_candidato_id', $tipo->id)
        ->set('cif_empresa', 'X99999999')
        ->assertSet('empresaEncontrada', false)
        ->assertSet('razon_social_empresa', '');
});

it('NO crea el candidato ni la empresa si el CIF no existe', function () {
    $tipo = tipoOrganizadora();

    Livewire::test(CandidatoForm::class)
        ->set('tipo_candidato_id', $tipo->id)
        ->set('nombre_contacto', 'Juan')
        ->set('email', 'juan@example.com')
        ->set('cif_empresa', 'X99999999')
        ->call('save')
        ->assertHasErrors('cif_empresa');

    expect(Candidato::count())->toBe(0);
    expect(Empresa::where('cif', 'X99999999')->exists())->toBeFalse();
});

it('crea el candidato usando la empresa existente, sin duplicarla', function () {
    $tipo = tipoOrganizadora();
    $empresa = Empresa::factory()->create(['cif' => 'B12345678', 'razon_social' => 'Acme SL']);

    Livewire::test(CandidatoForm::class)
        ->set('tipo_candidato_id', $tipo->id)
        ->set('nombre_contacto', 'Juan')
        ->set('email', 'juan@example.com')
        ->set('cif_empresa', 'B12345678')
        ->call('save');

    expect(Empresa::where('cif', 'B12345678')->count())->toBe(1);
    $cand = Candidato::first();
    expect($cand)->not->toBeNull();
    expect($cand->empresa_id)->toBe($empresa->id);
});

/**
 * Empresa externa: bonifica por su cuenta, nosotros no calculamos su crédito.
 * Sus datos se teclean a mano y se guardan en `empresas_externas`, nunca en `empresas`.
 */
it('registra la empresa externa tecleada sin darla de alta en el listado de empresas', function () {
    $tipo = TipoCandidato::factory()->create(['codigo' => 'empresa_externa', 'nombre' => 'Empresa Externa']);

    Livewire::test(CandidatoForm::class)
        ->set('tipo_candidato_id', $tipo->id)
        ->set('nombre_contacto', 'Ana')
        ->set('email', 'ana@example.com')
        ->set('cif_empresa', 'a-123456789')
        ->set('razon_social_empresa', 'Maquinaria SL')
        ->call('save')
        ->assertHasNoErrors();

    $externa = \App\Models\EmpresaExterna::where('cif', 'A123456789')->first();

    expect($externa)->not->toBeNull()
        ->and($externa->razon_social)->toBe('Maquinaria SL')
        // Lo importante: NO aparece en el universo FUNDAE nuestro
        ->and(Empresa::where('cif', 'A123456789')->exists())->toBeFalse();

    $cand = Candidato::first();
    expect($cand->empresa_externa_id)->toBe($externa->id)
        ->and($cand->empresa_id)->toBeNull();
});

it('exige CIF y razón social a la empresa externa', function () {
    $tipo = TipoCandidato::factory()->create(['codigo' => 'empresa_externa', 'nombre' => 'Empresa Externa']);

    Livewire::test(CandidatoForm::class)
        ->set('tipo_candidato_id', $tipo->id)
        ->set('nombre_contacto', 'Ana')
        ->set('email', 'ana@example.com')
        ->set('cif_empresa', 'A123456789')
        ->set('razon_social_empresa', '')
        ->call('save')
        ->assertHasErrors('razon_social_empresa');

    expect(Candidato::count())->toBe(0);
});

it('no muestra el buscador de empresa a un candidato de empresa externa', function () {
    $tipo = TipoCandidato::factory()->create(['codigo' => 'empresa_externa', 'nombre' => 'Empresa Externa']);

    $html = Livewire::test(CandidatoForm::class)
        ->set('tipo_candidato_id', $tipo->id)
        ->html();

    expect($html)->not->toContain('Buscar')
        ->toContain('no se da de alta en el listado de Empresas');
});
