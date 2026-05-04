<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlumnoLegacyCurso extends Model
{
    protected $table = 'alumnos_legacy_cursos';

    protected $fillable = [
        'nif',
        'course_id',
        'curso_titulo',
        'curso_short_name',
        'curso_horas',
        'fecha_inicio',
        'fecha_fin',
        'estado_curso',
        'resultado',
        'formation_group_alpha',
        'formation_group_number',
        'grupo_id_fundae',
        'origen_enriquecimiento',
        'legacy_company_text',
        'legacy_cif_resuelto',
        'source_mc_id',
        'source_mem_id',
        'imported_at',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'imported_at' => 'datetime',
    ];
}
