<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
