<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmpresaAnterior extends Model
{
    protected $table = 'empresas_anterior';

    protected $fillable = [
        'expediente',
        'cif',
        'razon_social',
        'plantilla_media',
        'reserva',
        'importe_reserva_2023',
        'importe_reserva_2024',
        'credito_asignado',
        'credito_dispuesto',
        'credito_disponible',
        'tgss',
        'cofinanciacion_privada_exigido',
        'cofinanciacion_privada_cumplido',
        'cnae',
        'convenio',
        'pyme',
        'nueva_creacion',
        'poblacion',
        'telefono',
        'email',
        'anulada',
        'bloqueada',
        'nuevo',
        'fecha_creacion',
        'actualizacion',
    ];

    protected $casts = [
        'plantilla_media' => 'integer',
        'importe_reserva_2023' => 'decimal:2',
        'importe_reserva_2024' => 'decimal:2',
        'credito_asignado' => 'decimal:2',
        'credito_dispuesto' => 'decimal:2',
        'credito_disponible' => 'decimal:2',
        'tgss' => 'decimal:2',
        'cofinanciacion_privada_exigido' => 'decimal:2',
        'cofinanciacion_privada_cumplido' => 'decimal:2',
        'nuevo' => 'boolean',
        'fecha_creacion' => 'datetime',
        'actualizacion' => 'datetime',
    ];

    public function grupos(): HasMany
    {
        return $this->hasMany(GrupoAnterior::class, 'cif', 'cif');
    }

    public function scopeConDatos($query)
    {
        return $query->whereNotNull('cif')
            ->where('cif', '!=', '')
            ->whereNotNull('razon_social')
            ->where('razon_social', '!=', '');
    }

    public function scopeSinGrupos($query)
    {
        return $query->whereDoesntHave('grupos');
    }

    public function getSaldoFormateadoAttribute(): string
    {
        return number_format($this->credito_disponible, 2, ',', '.') . ' €';
    }
}
