<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BonificadoEmailEnvio extends Model
{
    protected $table = 'bonificado_email_envios';

    protected $fillable = [
        'nif',
        'cif',
        'email_destinatario',
        'email_cc_empresa',
        'email_cc_admin',
        'nombre_participante',
        'razon_social',
        'saldo_enviado',
        'metodo',
        'modo_prueba',
        'enviado_at',
    ];

    protected $casts = [
        'enviado_at' => 'datetime',
        'saldo_enviado' => 'decimal:2',
        'modo_prueba' => 'boolean',
    ];
}
