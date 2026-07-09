<?php

namespace Database\Factories;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Empresa>
 */
class EmpresaFactory extends Factory
{
    protected $model = Empresa::class;

    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(10000000, 99999999);

        return [
            'cif'             => 'B' . $n,
            'razon_social'    => '[DEMO] ' . fake()->company(),
            'plantilla_media' => fake()->numberBetween(1, 50),
            'pyme'            => 'SI',
            'nueva_creacion'  => 'NO',
            'anulada'         => 'NO',
            'bloqueada'       => 'NO',
            'nuevo'           => true,
        ];
    }
}
