<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    use HasFactory;

    protected $table = 'empresas';

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

    /**
     * Relación con grupos de formación
     */
    public function grupos(): HasMany
    {
        return $this->hasMany(Grupo::class, 'cif', 'cif');
    }

    /**
     * Scope para empresas PYME
     */
    public function scopePyme($query)
    {
        return $query->where('pyme', 'SI');
    }

    /**
     * Scope para empresas bloqueadas
     */
    public function scopeBloqueadas($query)
    {
        return $query->where('bloqueada', 'SI');
    }

    /**
     * Scope para empresas no bloqueadas
     */
    public function scopeActivas($query)
    {
        return $query->where('bloqueada', 'NO');
    }

    /**
     * Scope para empresas de nueva creación
     */
    public function scopeNuevaCreacion($query)
    {
        return $query->where('nueva_creacion', 'SI');
    }

    /**
     * Scope para empresas sin grupos
     */
    public function scopeSinGrupos($query)
    {
        return $query->whereDoesntHave('grupos');
    }

    /**
     * Scope para excluir registros vacíos
     */
    public function scopeConDatos($query)
    {
        return $query->whereNotNull('cif')
            ->where('cif', '!=', '')
            ->whereNotNull('razon_social')
            ->where('razon_social', '!=', '');
    }

    /**
     * Verificar si la empresa tiene grupos
     */
    public function tieneGrupos(): bool
    {
        return $this->grupos()->exists();
    }

    /**
     * Obtener el saldo formateado
     */
    public function getSaldoFormateadoAttribute(): string
    {
        return number_format($this->credito_disponible, 2, ',', '.') . ' €';
    }

    /**
     * Verificar si está bloqueada
     */
    public function estaBloqueada(): bool
    {
        $valor = mb_strtolower($this->bloqueada, 'UTF-8');
        $valor = str_replace(['í', 'Í'], 'i', $valor);
        return $valor === 'si';
    }
}
