<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlumnoLegacyPool extends Model
{
    protected $table = 'alumnos_legacy_pool';

    protected $fillable = [
        'nif',
        'nombre',
        'apellido1',
        'apellido2',
        'email',
        'telefono',
        'niss',
        'fecha_nacimiento',
        'nivel_estudios',
        'categoria_profesional',
        'grupo_cotizacion_tgss',
        'legacy_nid',
        'legacy_company_text',
        'legacy_cif_resuelto',
        'source_mem_id',
        'imported_at',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'imported_at' => 'datetime',
    ];
}
