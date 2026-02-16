<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrupoAnterior extends Model
{
    protected $table = 'grupos_anterior';

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

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(EmpresaAnterior::class, 'cif', 'cif');
    }

    public function scopeConCif($query)
    {
        return $query->whereNotNull('cif')->where('cif', '!=', '');
    }

    public function getInicioFormateadoAttribute(): string
    {
        return $this->inicio ? $this->inicio->format('d/m/Y') : '';
    }

    public function getFinFormateadoAttribute(): string
    {
        return $this->fin ? $this->fin->format('d/m/Y') : '';
    }
}
