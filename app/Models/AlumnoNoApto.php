<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlumnoNoApto extends Model
{
    use HasFactory;

    protected $table = 'alumno_no_aptos';

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_OFRECIDO = 'ofrecido';
    public const ESTADO_ACEPTADO = 'aceptado';
    public const ESTADO_REINICIADO = 'reiniciado';
    public const ESTADO_CADUCADO = 'caducado';
    public const ESTADO_RECHAZADO = 'rechazado';

    protected $fillable = [
        'alumno_id',
        'grupo_formativo_id',
        'grupo_formativo_alumno_id',
        'matricula_autonoma_id',
        'origen_clave',
        'moodle_user_id',
        'moodle_course_id',
        'nota_total',
        'nota_max',
        'fecha_fin_curso',
        'fecha_deteccion',
        'reinicio_estado',
        'reinicio_aceptado_at',
        'reinicio_cerrado_at',
        'reinicio_cerrado_por_user_id',
        'notas',
    ];

    protected $casts = [
        'nota_total' => 'decimal:2',
        'nota_max' => 'decimal:2',
        'fecha_fin_curso' => 'date',
        'fecha_deteccion' => 'datetime',
        'reinicio_aceptado_at' => 'datetime',
        'reinicio_cerrado_at' => 'datetime',
    ];

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function grupoFormativo(): BelongsTo
    {
        return $this->belongsTo(GrupoFormativo::class);
    }

    public function pivot(): BelongsTo
    {
        return $this->belongsTo(GrupoFormativoAlumno::class, 'grupo_formativo_alumno_id');
    }

    public function matriculaAutonoma(): BelongsTo
    {
        return $this->belongsTo(MatriculaAutonoma::class, 'matricula_autonoma_id');
    }

    /**
     * Origen del que salió este No apto: grupo formativo o matrícula individual.
     */
    public function origen(): ?\App\Contracts\OrigenMatriculaMoodle
    {
        return $this->pivot ?? $this->matriculaAutonoma;
    }

    /** Acción formativa del curso suspendido, venga de donde venga. */
    public function getAccionCursoAttribute(): ?AccionFormativa
    {
        return $this->origen()?->accionFormativaMatricula();
    }

    public function ofrecimientos(): HasMany
    {
        return $this->hasMany(AlumnoReinicioOfrecimiento::class)->orderBy('fecha_envio');
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reinicio_cerrado_por_user_id');
    }

    public function scopePendientesOfrecimiento($query)
    {
        return $query->whereIn('reinicio_estado', [self::ESTADO_PENDIENTE, self::ESTADO_OFRECIDO]);
    }

    public function getNotaPorcentajeAttribute(): ?float
    {
        if ($this->nota_total === null || $this->nota_max === null || (float) $this->nota_max <= 0) {
            return null;
        }
        return round(((float) $this->nota_total / (float) $this->nota_max) * 100, 1);
    }
}
