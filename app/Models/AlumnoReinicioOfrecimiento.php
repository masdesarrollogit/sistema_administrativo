<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlumnoReinicioOfrecimiento extends Model
{
    use HasFactory;

    protected $table = 'alumno_reinicio_ofrecimientos';

    protected $fillable = [
        'alumno_no_apto_id',
        'num_ofrecimiento',
        'fecha_envio',
        'canal',
        'destinatario_email',
        'exitoso',
        'error_message',
    ];

    protected $casts = [
        'num_ofrecimiento' => 'integer',
        'fecha_envio' => 'datetime',
        'exitoso' => 'boolean',
    ];

    public function noApto(): BelongsTo
    {
        return $this->belongsTo(AlumnoNoApto::class, 'alumno_no_apto_id');
    }
}
