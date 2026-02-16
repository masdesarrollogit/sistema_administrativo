<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grupo extends Model
{
    use HasFactory;

    protected $table = 'grupos';

    protected $fillable = [
        'grupo_id',
        'codigo_grupo',
        'codigo_grupo_accion_formativa',
        'tipo_accion_formativa',
        'denominacion',
        'cif',
        'inicio',
        'fin',
        'not_inicio',
        'not_final',
        'modalidad',
        'duracion',
        'estado',
        'incidencia',
        'medios_formacion',
        'numero_participantes',
        'centro_formacion',
        'centro_imparticion',
        'centro_gestor_plataforma',
    ];

    protected $casts = [
        'inicio' => 'date',
        'fin' => 'date',
        'not_inicio' => 'date',
        'not_final' => 'date',
        'duracion' => 'integer',
        'numero_participantes' => 'integer',
    ];

    /**
     * Relación con la empresa
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'cif', 'cif');
    }

    /**
     * Scope para grupos finalizados
     */
    public function scopeFinalizados($query)
    {
        return $query->where('estado', 'Finalizado');
    }

    /**
     * Scope para grupos válidos
     */
    public function scopeValidos($query)
    {
        return $query->where('estado', 'Válido');
    }

    /**
     * Scope para teleformación
     */
    public function scopeTeleformacion($query)
    {
        return $query->where('modalidad', 'Teleformación');
    }

    /**
     * Scope para presencial
     */
    public function scopePresencial($query)
    {
        return $query->where('modalidad', 'Presencial');
    }

    /**
     * Scope para grupos con CIF válido
     */
    public function scopeConCif($query)
    {
        return $query->whereNotNull('cif')->where('cif', '!=', '');
    }

    /**
     * Verificar si es un registro "NO VA"
     */
    public static function esNoVa(string $denominacion): bool
    {
        $denominacion = strtoupper(trim($denominacion));
        return strpos($denominacion, 'NO VA') === 0;
    }

    /**
     * Extraer CIF de la denominación
     */
    public static function extraerCif(string $denominacion): string
    {
        if (preg_match('/^([A-Za-z0-9]+)\s/', trim($denominacion), $matches)) {
            $cif = $matches[1];
            if (strlen($cif) >= 8) {
                return $cif;
            }
        }
        return '';
    }

    /**
     * Obtener fecha de inicio formateada
     */
    public function getInicioFormateadoAttribute(): string
    {
        return $this->inicio ? $this->inicio->format('d/m/Y') : '';
    }

    /**
     * Obtener fecha de fin formateada
     */
    public function getFinFormateadoAttribute(): string
    {
        return $this->fin ? $this->fin->format('d/m/Y') : '';
    }

    /**
     * Obtener el color según el estado
     */
    public function getEstadoColorAttribute(): string
    {
        return match($this->estado) {
            'Finalizado' => 'text-green-600',
            'Válido' => 'text-blue-600',
            'Modificado' => 'text-orange-500',
            default => 'text-gray-600',
        };
    }
}
