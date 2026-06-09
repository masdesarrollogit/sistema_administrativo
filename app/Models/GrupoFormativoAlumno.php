<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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
        'ficha_inscripcion_path',
        'ficha_inscripcion_tipo',
        'ficha_inscripcion_subida_en',
    ];

    protected $casts = [
        'moodle_user_id' => 'integer',
        'intentos_moodle' => 'integer',
        'ficha_inscripcion_subida_en' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Al borrar el pivot, eliminar el PDF asociado para evitar archivos huérfanos.
        static::deleting(function (self $pivot) {
            if ($pivot->ficha_inscripcion_path) {
                Storage::disk('local')->delete($pivot->ficha_inscripcion_path);
            }
        });
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function grupoFormativo(): BelongsTo
    {
        return $this->belongsTo(GrupoFormativo::class, 'grupo_formativo_id');
    }
}
