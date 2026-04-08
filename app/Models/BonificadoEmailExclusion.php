<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonificadoEmailExclusion extends Model
{
    protected $table = 'bonificado_email_exclusiones';

    protected $fillable = [
        'nif',
        'nombre',
        'motivo',
        'excluido_por',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'excluido_por');
    }

    /**
     * Devuelve array de NIFs excluidos del envío de email.
     */
    public static function nifsExcluidos(): array
    {
        return static::pluck('nif')->toArray();
    }
}
