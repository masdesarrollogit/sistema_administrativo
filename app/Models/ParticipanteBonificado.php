<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParticipanteBonificado extends Model
{
    protected $table = 'participantes_bonificados';

    protected $fillable = [
        'nif_participante',
        'niss',
        'nombre',
        'estado',
        'cif',
        'id_codigo_grupo',
        'codigo_pif',
        'fecha_inicio',
        'fecha_fin',
        'estado_grupo',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin'    => 'date',
    ];
}
