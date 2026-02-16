<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoCandidato extends Model
{
    use HasFactory;

    protected $table = 'tipos_candidato';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'activo',
        'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    /**
     * Relación con tipos de requisito
     */
    public function tiposRequisito(): HasMany
    {
        return $this->hasMany(TipoRequisito::class, 'tipo_candidato_id');
    }

    /**
     * Relación con candidatos
     */
    public function candidatos(): HasMany
    {
        return $this->hasMany(Candidato::class, 'tipo_candidato_id');
    }

    /**
     * Scope para tipos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true)->orderBy('orden');
    }

    /**
     * Obtener tipos de requisito activos y obligatorios
     */
    public function requisitosObligatorios()
    {
        return $this->tiposRequisito()
            ->where('activo', true)
            ->where('obligatorio', true)
            ->orderBy('orden')
            ->get();
    }
}
