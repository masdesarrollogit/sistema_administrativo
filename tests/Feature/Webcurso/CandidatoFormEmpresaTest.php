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
