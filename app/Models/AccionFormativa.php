<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccionFormativa extends Model
{
    use HasFactory;

    protected $table = 'acciones_formativas';

    protected $fillable = [
        'numero_accion',
        'denominacion',
        'modalidad',
        'tipo',
        'estado',
        'horas',
        'nivel_formacion',
        'nif_proveedor_plataforma',
        'url_plataforma',
        'clave_acceso',
        'usuario_supervision',
        'area_profesional',
        'codigo_actividad',
        'cod_grupo_accion',
        'objetivos',
        'contenidos',
    ];

    protected $casts = [
        'numero_accion' => 'integer',
        'horas' => 'integer',
    ];

    /**
     * Cursos de Moodle vinculados a esta acción formativa (relación 1:N)
     */
    public function moodleCursos(): HasMany
    {
        return $this->hasMany(AccionFormativaMoodleCurso::class, 'accion_formativa_id');
    }

    /**
     * Grupos formativos asociados a esta acción formativa
     */
    public function gruposFormativos(): HasMany
    {
        return $this->hasMany(GrupoFormativo::class, 'accion_formativa_id');
    }

    /**
     * Scope para acciones activas (estado = Alta)
     */
    public function scopeActivas($query)
    {
        return $query->where('estado', 'Alta');
    }

    /**
     * Scope para acciones de teleformación
     */
    public function scopeTeleformacion($query)
    {
        return $query->where('modalidad', 'Teleformación');
    }

    /**
     * Obtener la denominación sin el código de horas y plataforma
     * Ej: "Excel 365 avanzado con tablas dinámicas 60h m" → "Excel 365 avanzado con tablas dinámicas"
     */
    public function getDenominacionLimpiaAttribute(): string
    {
        return preg_replace('/\s+\d+h(\s+[a-z])?$/i', '', $this->denominacion);
    }

    /**
     * Obtener el código de plataforma.
     * Fuente principal: nif_proveedor_plataforma (importado del XLS).
     * Fallback: letra al final de la denominación (ej: "60h m" → 'm').
     */
    public function getCodigoPlataformaAttribute(): ?string
    {
        // Fuente fiable: NIF del proveedor de plataforma
        if ($this->nif_proveedor_plataforma === 'B65828857') {
            return 'm';
        }
        if ($this->nif_proveedor_plataforma === 'B41273392') {
            return 'a';
        }

        // Fallback: extraer de la denominación
        if (preg_match('/\s+\d+h\s+([a-z])$/i', $this->denominacion, $matches)) {
            return strtolower($matches[1]);
        }

        return null;
    }
}
