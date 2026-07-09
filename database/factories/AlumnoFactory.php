<?php

namespace Database\Factories;

use App\Models\Alumno;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alumno>
 */
class AlumnoFactory extends Factory
{
    protected $model = Alumno::class;

    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(10000000, 99999999);

        return [
            'empresa_id' => Empresa::factory(),
            'nombre'     => fake()->firstName(),
            'apellido1'  => fake()->lastName(),
            'apellido2'  => fake()->lastName(),
            'nif'        => $n . 'A',
            'email'      => fake()->unique()->safeEmail(),
            'telefono'   => '6' . fake()->numerify('########'),
            'activo'     => true,
        ];
    }
}
