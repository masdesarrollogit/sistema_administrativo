<?php

namespace Database\Factories;

use App\Models\AccionFormativa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccionFormativa>
 */
class AccionFormativaFactory extends Factory
{
    protected $model = AccionFormativa::class;

    public function definition(): array
    {
        $horas = fake()->randomElement([25, 40, 60, 80]);

        return [
            'numero_accion'             => fake()->unique()->numberBetween(900, 9999),
            'denominacion'              => '[DEMO] ' . fake()->words(3, true) . " {$horas}h m",
            'modalidad'                 => 'Teleformación',
            'estado'                    => 'Alta',
            'horas'                     => $horas,
            'nif_proveedor_plataforma'  => 'B65828857',
            'url_plataforma'            => 'aula.1curso.com',
            'cod_grupo_accion'          => '068-06',
        ];
    }
}
