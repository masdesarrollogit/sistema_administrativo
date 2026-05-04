<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alumno extends Model
{
    use HasFactory;

    protected $table = 'alumnos';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'apellido1',
        'apellido2',
        'nif',
        'email',
        'telefono',
        'niss',
        'ccc',
        'grupo_cotizacion_tgss',
        'fecha_nacimiento',
        'sexo',
        'nivel_estudios',
        'categoria_profesional',
        'jornada_laboral',
        'activo',
        'datos_pendientes',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'nivel_estudios' => 'integer',
        'categoria_profesional' => 'integer',
        'jornada_laboral' => 'integer',
        'activo' => 'boolean',
        'datos_pendientes' => 'boolean',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function matriculasAutonomas(): HasMany
    {
        return $this->hasMany(MatriculaAutonoma::class, 'alumno_id');
    }

    public function participantesBonificados(): HasMany
    {
        return $this->hasMany(ParticipanteBonificado::class, 'nif_participante', 'nif');
    }

    public function cursosLegacy(): HasMany
    {
        return $this->hasMany(AlumnoLegacyCurso::class, 'nif', 'nif');
    }

    public function gruposFormativos(): BelongsToMany
    {
        return $this->belongsToMany(GrupoFormativo::class, 'grupo_formativo_alumno')
            ->withPivot(['moodle_user_id', 'moodle_username', 'estado_moodle'])
            ->withTimestamps();
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Alumnos sin grupo en_curso (disponibles para nueva matrícula)
     */
    public function scopeDisponibles($query)
    {
        return $query->where('activo', true)
            ->whereDoesntHave('gruposFormativos', function ($q) {
                $q->whereIn('estado', ['abierto', 'comunicado', 'en_curso']);
            });
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombre} {$this->apellido1} {$this->apellido2}");
    }

    /**
     * Verificar si el alumno tiene un grupo activo cuyas fechas se solapan con el rango dado.
     * Si no se pasan fechas, verifica solapamiento con cualquier grupo activo.
     *
     * Regla: un alumno no puede estar en dos grupos simultáneos (mismo período de tiempo).
     * Puede estar en grupos consecutivos aunque sea la misma acción formativa.
     */
    public function tieneGrupoActivoEnPeriodo(?string $fechaInicio = null, ?string $fechaFin = null, ?int $excluirGrupoId = null): bool
    {
        $query = $this->gruposFormativos()
            ->whereIn('estado', ['abierto', 'comunicado', 'en_curso']);

        if ($excluirGrupoId) {
            $query->where('grupos_formativos.id', '!=', $excluirGrupoId);
        }

        if ($fechaInicio && $fechaFin) {
            // Solapamiento: los rangos se solapan si inicio_A <= fin_B Y fin_A >= inicio_B
            $query->where('fecha_inicio', '<=', $fechaFin)
                  ->where('fecha_fin', '>=', $fechaInicio);
        }

        return $query->exists();
    }

    /**
     * @deprecated Usar tieneGrupoActivoEnPeriodo() con fechas del grupo destino.
     */
    public function tieneGrupoActivo(): bool
    {
        return $this->tieneGrupoActivoEnPeriodo();
    }
}
