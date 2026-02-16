<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificacionLog extends Model
{
    use HasFactory;

    protected $table = 'notificaciones_log';

    public $timestamps = false;

    protected $fillable = [
        'candidato_id',
        'requisitos_faltantes',
        'tipo_notificacion',
        'canal',
        'destinatario_email',
        'enviado_at',
        'exitoso',
        'error_message',
    ];

    protected $casts = [
        'requisitos_faltantes' => 'array',
        'enviado_at' => 'datetime',
        'exitoso' => 'boolean',
    ];

    /**
     * Relación con candidato
     */
    public function candidato(): BelongsTo
    {
        return $this->belongsTo(Candidato::class, 'candidato_id');
    }

    /**
     * Registrar envío exitoso
     */
    public static function registrarExito(
        int $candidatoId,
        array $requisitosFaltantes,
        string $destinatarioEmail
    ): self {
        return self::create([
            'candidato_id' => $candidatoId,
            'requisitos_faltantes' => $requisitosFaltantes,
            'destinatario_email' => $destinatarioEmail,
            'enviado_at' => now(),
            'exitoso' => true,
        ]);
    }

    /**
     * Registrar error de envío
     */
    public static function registrarError(
        int $candidatoId,
        array $requisitosFaltantes,
        string $destinatarioEmail,
        string $errorMessage
    ): self {
        return self::create([
            'candidato_id' => $candidatoId,
            'requisitos_faltantes' => $requisitosFaltantes,
            'destinatario_email' => $destinatarioEmail,
            'enviado_at' => now(),
            'exitoso' => false,
            'error_message' => $errorMessage,
        ]);
    }
}
