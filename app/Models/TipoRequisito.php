<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoRequisito extends Model
{
    use HasFactory;

    protected $table = 'tipos_requisito';

    protected $fillable = [
        'tipo_candidato_id',
        'codigo',
        'nombre',
        'descripcion',
        'orden',
        'obligatorio',
        'activo',
    ];

    protected $casts = [
        'orden' => 'integer',
        'obligatorio' => 'boolean',
        'activo' => 'boolean',
    ];

    /**
     * Relación con tipo de candidato
     */
    public function tipoCandidato(): BelongsTo
    {
        return $this->belongsTo(TipoCandidato::class, 'tipo_candidato_id');
    }

    /**
     * Relación con requisitos de candidatos
     */
    public function requisitosCandidato(): HasMany
    {
        return $this->hasMany(RequisitoCandidato::class, 'tipo_requisito_id');
    }

    /**
     * Scope para requisitos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true)->orderBy('orden');
    }

    /**
     * Scope para requisitos obligatorios
     */
    public function scopeObligatorios($query)
    {
        return $query->where('obligatorio', true);
    }
}
