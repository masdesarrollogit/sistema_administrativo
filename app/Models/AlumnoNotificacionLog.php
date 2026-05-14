<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlumnoNotificacionLog extends Model
{
    use HasFactory;

    protected $table = 'alumno_notificaciones_log';

    public $timestamps = false;

    public const TIPO_ALUMNO_NO_CONECTADO = 'alumno_no_conectado';
    public const TIPO_ALUMNO_INACTIVO = 'alumno_inactivo';
    public const TIPO_ALUMNO_RIESGO_CRITICO = 'alumno_riesgo_critico';
    public const TIPO_ALUMNO_PRE_CIERRE = 'alumno_pre_cierre';
    public const TIPO_ALUMNO_APTO_SIN_EXAMEN = 'alumno_apto_sin_examen';
    public const TIPO_ALUMNO_APTO = 'alumno_apto';
    public const TIPO_ALUMNO_NO_APTO = 'alumno_no_apto';
    public const TIPO_TUTOR_REPORTE_SEMANAL = 'tutor_reporte_semanal';

    protected $fillable = [
        'alumno_id',
        'tutor_id',
        'grupo_formativo_id',
        'tipo',
        'fase',
        'canal',
        'destinatario_email',
        'payload',
        'exitoso',
        'error_message',
        'enviado_at',
    ];

    protected $casts = [
        'fase' => 'integer',
        'payload' => 'array',
        'exitoso' => 'boolean',
        'enviado_at' => 'datetime',
    ];

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Tutor::class, 'tutor_id');
    }

    public function grupoFormativo(): BelongsTo
    {
        return $this->belongsTo(GrupoFormativo::class, 'grupo_formativo_id');
    }

    public static function registrarExito(array $datos): self
    {
        return self::create(array_merge($datos, [
            'exitoso' => true,
            'enviado_at' => now(),
        ]));
    }

    public static function registrarError(array $datos, string $errorMessage): self
    {
        return self::create(array_merge($datos, [
            'exitoso' => false,
            'error_message' => $errorMessage,
            'enviado_at' => now(),
        ]));
    }

    public function scopeAlumnoNoConectado($query)
    {
        return $query->where('tipo', self::TIPO_ALUMNO_NO_CONECTADO);
    }
}
