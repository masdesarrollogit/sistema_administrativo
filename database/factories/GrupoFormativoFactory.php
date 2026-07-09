<?php

namespace Database\Factories;

use App\Models\AccionFormativa;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\GrupoFormativo;
use App\Models\Tutor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GrupoFormativo>
 */
class GrupoFormativoFactory extends Factory
{
    protected $model = GrupoFormativo::class;

    public function definition(): array
    {
        return [
            'candidato_id'        => Candidato::factory(),
            'accion_formativa_id' => AccionFormativa::factory(),
            'tutor_id'            => Tutor::factory(),
            'empresa_id'          => Empresa::factory(),
            'tramo_horario'       => 'tramo_2',
            'fecha_inicio'        => now()->subDays(10)->toDateString(),
            'fecha_fin'           => now()->addDays(20)->toDateString(),
            'jornada_laboral'     => 1,
            'estado'              => 'en_curso',
        ];
    }
}
