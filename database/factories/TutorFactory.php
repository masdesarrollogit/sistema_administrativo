<?php

namespace Database\Factories;

use App\Models\Tutor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tutor>
 */
class TutorFactory extends Factory
{
    protected $model = Tutor::class;

    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(10000000, 99999999);

        return [
            'nombre'          => fake()->firstName(),
            'apellido1'       => fake()->lastName(),
            'apellido2'       => fake()->lastName(),
            'nif'             => $n . 'A',
            'email'           => fake()->unique()->safeEmail(),
            'telefono'        => '6' . fake()->numerify('########'),
            'tipo'            => 'interno',
            'activo'          => true,
            'moodle_username' => 'tutor' . strtolower(fake()->unique()->lexify('?????')),
        ];
    }
}
