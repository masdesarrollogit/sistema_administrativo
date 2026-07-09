<?php

namespace Database\Factories;

use App\Models\TipoCandidato;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoCandidato>
 */
class TipoCandidatoFactory extends Factory
{
    protected $model = TipoCandidato::class;

    public function definition(): array
    {
        return [
            'codigo'      => 'empresa_organizadora',
            'nombre'      => 'Empresa Bonificable (Organizadora)',
            'descripcion' => 'DEMO',
            'activo'      => true,
            'orden'       => 1,
        ];
    }
}
