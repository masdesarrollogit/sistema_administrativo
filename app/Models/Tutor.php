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
     * Alumnos activos de este tutor en un tramo determinado.
     * Para validar el límite de 80 participantes por tutor por tramo.
     */
    public function alumnosEnTramo(string $tramo): int
    {
        return GrupoFormativo::alumnosPorTutorYTramo($this->id, $tramo);
    }

    /**
     * Verificar si el tutor puede aceptar más participantes en un tramo.
     */
    public function puedeAceptarEnTramo(string $tramo, int $nuevosAlumnos = 1): bool
    {
        return ($this->alumnosEnTramo($tramo) + $nuevosAlumnos) <= 80;
    }
}
