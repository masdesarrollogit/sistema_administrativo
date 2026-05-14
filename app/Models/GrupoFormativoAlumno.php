<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrupoFormativoAlumno extends Model
{
    protected $table = 'grupo_formativo_alumno';

    protected $fillable = [
        'grupo_formativo_id',
        'alumno_id',
        'moodle_user_id',
        'moodle_username',
        'estado_moodle',
        'intentos_moodle',
        'ultimo_error_moodle',
    ];

    protected $casts = [
        'moodle_user_id' => 'integer',
        'intentos_moodle' => 'integer',
    ];

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function grupoFormativo(): BelongsTo
    {
        return $this->belongsTo(GrupoFormativo::class, 'grupo_formativo_id');
    }
}
