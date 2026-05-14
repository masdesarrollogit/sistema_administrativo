<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlumnoProgresoMoodle extends Model
{
    use HasFactory;

    protected $table = 'alumno_progreso_moodle';

    protected $fillable = [
        'alumno_id',
        'grupo_formativo_alumno_id',
        'moodle_user_id',
        'moodle_course_id',
        'lastaccess_global',
        'lastaccess_curso',
        'nunca_entro_sitio',
        'nunca_entro_curso',
        'progress',
        'completed',
        'aprobado',
        'cuestionario_final_realizado',
        'nota_total',
        'nota_max',
        'dias_inactivo',
        'inactivo',
        'riesgo_critico',
        'pre_cierre',
        'apto_sin_examen',
        'pct_tiempo_transcurrido',
        'fecha_snapshot',
        'ultimo_refresh_at',
        'fuente',
    ];

    protected $casts = [
        'lastaccess_global' => 'integer',
        'lastaccess_curso' => 'integer',
        'nunca_entro_sitio' => 'boolean',
        'nunca_entro_curso' => 'boolean',
        'progress' => 'decimal:2',
        'completed' => 'boolean',
        'aprobado' => 'boolean',
        'cuestionario_final_realizado' => 'boolean',
        'nota_total' => 'decimal:2',
        'nota_max' => 'decimal:2',
        'dias_inactivo' => 'integer',
        'inactivo' => 'boolean',
        'riesgo_critico' => 'boolean',
        'pre_cierre' => 'boolean',
        'apto_sin_examen' => 'boolean',
        'pct_tiempo_transcurrido' => 'decimal:2',
        'fecha_snapshot' => 'date',
        'ultimo_refresh_at' => 'datetime',
    ];

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function pivot(): BelongsTo
    {
        return $this->belongsTo(GrupoFormativoAlumno::class, 'grupo_formativo_alumno_id');
    }

    public function calificaciones(): HasMany
    {
        return $this->hasMany(AlumnoCalificacionMoodle::class, 'alumno_progreso_moodle_id');
    }

    public function scopeDeHoy($query)
    {
        return $query->whereDate(
            'fecha_snapshot',
            \Carbon\CarbonImmutable::now('Europe/Madrid')->toDateString(),
        );
    }

    public function scopeNoConectados($query)
    {
        return $query->where(function ($q) {
            $q->where('nunca_entro_sitio', true)
              ->orWhere('nunca_entro_curso', true);
        });
    }

    public function scopeInactivos($query)
    {
        return $query->where('inactivo', true);
    }

    public function scopeRiesgoCritico($query)
    {
        return $query->where('riesgo_critico', true);
    }

    public function scopePreCierre($query)
    {
        return $query->where('pre_cierre', true);
    }

    public function scopeAptoSinExamen($query)
    {
        return $query->where('apto_sin_examen', true);
    }

    public function getCategoriaSemaforoAttribute(): ?string
    {
        if ($this->nunca_entro_sitio) {
            return 'gris_oscuro';
        }
        if ($this->nunca_entro_curso) {
            return 'gris_claro';
        }
        if ($this->pre_cierre) {
            return 'ambar_oscuro';
        }
        if ($this->apto_sin_examen) {
            return 'amarillo_claro';
        }
        if ($this->riesgo_critico) {
            return 'rojo';
        }
        if ($this->inactivo) {
            return 'indigo';
        }
        return 'verde';
    }

    public function getNotaTotalPorcentajeAttribute(): ?float
    {
        if ($this->nota_total === null || $this->nota_max === null || (float) $this->nota_max <= 0) {
            return null;
        }
        return round(((float) $this->nota_total / (float) $this->nota_max) * 100, 1);
    }
}
