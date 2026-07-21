<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tutor extends Model
{
    use HasFactory;

    protected $table = 'tutores';

    protected $fillable = [
        'nombre',
        'apellido1',
        'apellido2',
        'nif',
        'email',
        'telefono',
        'tipo',
        'activo',
        'moodle_username',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function gruposFormativos(): HasMany
    {
        return $this->hasMany(GrupoFormativo::class, 'tutor_id');
    }

    public function matriculasAutonomas(): HasMany
    {
        return $this->hasMany(MatriculaAutonoma::class, 'tutor_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeInternos($query)
    {
        return $query->where('tipo', 'interno');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombre} {$this->apellido1} {$this->apellido2}");
    }

    /**
     * Iniciales del tutor (para descripción FUNDAE e idnumber Moodle).
     */
    public function getInicialesAttribute(): string
    {
        return mb_strtoupper(mb_substr($this->nombre, 0, 1))
             . mb_strtoupper(mb_substr($this->apellido1, 0, 1));
    }

    /**
     * Alumnos de este tutor en un tramo que coinciden en el tiempo con un período dado.
     * Para validar el límite de 80 participantes SIMULTÁNEOS por tutor por tramo.
     * Sin fechas, cuenta todos los grupos activos del tramo.
     */
    public function alumnosEnTramo(
        string $tramo,
        string|\Carbon\Carbon|null $fechaInicio = null,
        string|\Carbon\Carbon|null $fechaFin = null,
        ?int $excluirGrupoId = null
    ): int {
        return GrupoFormativo::alumnosPorTutorYTramo($this->id, $tramo, $fechaInicio, $fechaFin, $excluirGrupoId);
    }

    /**
     * Carga actual del tutor en un tramo (alumnos en grupos activos HOY).
     * Pensado para el display de /webcurso/tutores.
     */
    public function cargaHoyEnTramo(string $tramo): int
    {
        $hoy = now()->toDateString();

        return $this->alumnosEnTramo($tramo, $hoy, $hoy);
    }

    /**
     * Verificar si el tutor puede aceptar más participantes en un tramo durante un período.
     * El límite de 80 se evalúa solo sobre los grupos que se solapan con [fechaInicio, fechaFin].
     */
    public function puedeAceptarEnTramo(
        string $tramo,
        int $nuevosAlumnos = 1,
        string|\Carbon\Carbon|null $fechaInicio = null,
        string|\Carbon\Carbon|null $fechaFin = null,
        ?int $excluirGrupoId = null
    ): bool {
        return ($this->alumnosEnTramo($tramo, $fechaInicio, $fechaFin, $excluirGrupoId) + $nuevosAlumnos) <= 80;
    }
}
