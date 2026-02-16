<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmpresaExterna extends Model
{
    use HasFactory;

    protected $table = 'empresas_externas';

    protected $fillable = [
        'cif',
        'razon_social',
        'email',
        'telefono',
        'direccion',
        'poblacion',
        'contacto_nombre',
        'notas',
    ];

    /**
     * Relación con candidatos
     */
    public function candidatos(): HasMany
    {
        return $this->hasMany(Candidato::class, 'empresa_externa_id');
    }

    /**
     * Buscar empresa externa por CIF
     */
    public static function buscarPorCif(string $cif): ?self
    {
        return self::where('cif', $cif)->first();
    }

    /**
     * Crear o actualizar empresa externa
     */
    public static function crearOActualizar(array $datos): self
    {
        return self::updateOrCreate(
            ['cif' => $datos['cif']],
            $datos
        );
    }
}
