<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mirror local de un contrato de encomienda firmado en el sistema externo.
 * Poblado por el comando `encomienda:sincronizar`.
 */
class EncomiendaContrato extends Model
{
    protected $table = 'encomienda_contratos';

    protected $fillable = [
        'source_id',
        'referencia_aceptacion',
        'empresa_cif',
        'empresa_razon_social',
        'empresa_domicilio',
        'empresa_localidad',
        'firmante_nombre',
        'firmante_nif',
        'firmante_cargo',
        'email',
        'telefono',
        'saldo_fundae',
        'tiene_rlt',
        'origen_externo',
        'estado_externo',
        'pdf_path',
        'pdf_hash',
        'aceptado_en',
        'empresa_id',
        'candidato_id',
        'estado_procesamiento',
        'error_message',
        'sincronizado_en',
        'descartado_en',
        'descartado_por',
    ];

    protected $casts = [
        'aceptado_en'     => 'datetime',
        'sincronizado_en' => 'datetime',
        'descartado_en'   => 'datetime',
    ];

    public function scopeActivos($query)
    {
        return $query->whereNull('descartado_en');
    }

    public function scopeDescartados($query)
    {
        return $query->whereNotNull('descartado_en');
    }

    public function estaDescartado(): bool
    {
        return $this->descartado_en !== null;
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function candidato(): BelongsTo
    {
        return $this->belongsTo(Candidato::class);
    }

    public function alumnos(): HasMany
    {
        return $this->hasMany(EncomiendaAlumnoStaging::class);
    }

    public function alumnosPendientes(): HasMany
    {
        return $this->alumnos()->where('estado', 'pendiente');
    }
}
