<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fila del índice inverso email → curso de Moodle (incluye matrículas caducadas).
 * Poblada por `encuestas-calidad:snapshot-moodle`. Ver migración
 * create_moodle_matricula_index_table y EncuestaCalidadService::resolverCursoDesdeIndiceMoodle.
 */
class MoodleMatriculaIndex extends Model
{
    protected $table = 'moodle_matricula_index';

    public $timestamps = false;

    protected $fillable = [
        'email',
        'moodle_course_id',
        'curso_fullname',
        'categoria_id',
        'curso_startdate',
        'curso_enddate',
        'ultimo_acceso',
        'tutor_label',
        'capturado_en',
    ];

    protected $casts = [
        'moodle_course_id' => 'integer',
        'categoria_id'     => 'integer',
        'curso_startdate'  => 'date',
        'curso_enddate'    => 'date',
        'ultimo_acceso'    => 'date',
        'capturado_en'     => 'datetime',
    ];
}
