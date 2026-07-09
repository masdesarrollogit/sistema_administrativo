<?php

namespace Database\Factories;

use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\TipoCandidato;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Candidato>
 */
class CandidatoFactory extends Factory
{
    protected $model = Candidato::class;

    public function definition(): array
    {
        return [
            'tipo_candidato_id' => TipoCandidato::factory(),
            'empresa_id'        => Empresa::factory(),
            'nombre_contacto'   => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'telefono'          => '6' . fake()->numerify('########'),
            'estatus'           => 'completo',
        ];
    }
}
