<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AccionFormativaMoodleCurso extends Model
{
    protected $table = 'accion_formativa_moodle_curso';

    protected $fillable = [
        'accion_formativa_id',
        'moodle_course_id',
        'moodle_fullname',
        'tipo',
    ];

    protected $casts = [
        'moodle_course_id' => 'integer',
    ];

    public function accionFormativa(): BelongsTo
    {
        return $this->belongsTo(AccionFormativa::class, 'accion_formativa_id');
    }

    /**
     * Tutores que imparten esta aula. Una misma aula puede darla mas de uno
     * (p.ej. "Power Bi Inicial 40 horas" la imparten Raquel y Alvaro).
     *
     * Se rellena sola: al matricular, la deteccion por rol de profesor en
     * Moodle anade el tutor del grupo si aun no estaba. Tambien es editable a
     * mano desde Acciones Formativas.
     */
    public function tutores(): BelongsToMany
    {
        return $this->belongsToMany(
            Tutor::class,
            'accion_formativa_moodle_curso_tutor',
            'accion_formativa_moodle_curso_id',
            'tutor_id'
        )->withTimestamps();
    }
}
