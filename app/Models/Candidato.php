<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Candidato extends Model
{
    use HasFactory;

    protected $table = 'candidatos';

    protected $fillable = [
        'tipo_candidato_id',
        'empresa_id',
        'empresa_externa_id',
        'nombre_contacto',
        'email',
        'telefono',
        'estatus',
        'fecha_inicio',
        'frecuencia_envio',
        'descripcion_personalizada',
        'ultimo_recordatorio',
        'recordatorios_enviados',
        'curso_referencia',
        'curso_nombre',
        'notas',
        'observacion',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'frecuencia_envio' => 'integer',
        'ultimo_recordatorio' => 'datetime',
        'recordatorios_enviados' => 'integer',
    ];

    /**
     * Relación con archivos adjuntos
     */
    public function archivos(): HasMany
    {
        return $this->hasMany(CandidatoArchivo::class, 'candidato_id');
    }

    /**
     * Relación con tipo de candidato
     */
    public function tipoCandidato(): BelongsTo
    {
        return $this->belongsTo(TipoCandidato::class, 'tipo_candidato_id');
    }

    /**
     * Relación con empresa (Tipo 1)
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    /**
     * Relación con empresa externa (Tipo 2)
     */
    public function empresaExterna(): BelongsTo
    {
        return $this->belongsTo(EmpresaExterna::class, 'empresa_externa_id');
    }

    /**
     * Relación con requisitos
     */
    public function requisitos(): HasMany
    {
        return $this->hasMany(RequisitoCandidato::class, 'candidato_id');
    }

    /**
     * Relación con notificaciones
     */
    public function notificaciones(): HasMany
    {
        return $this->hasMany(NotificacionLog::class, 'candidato_id');
    }

    /**
     * Scope para candidatos pendientes
     */
    public function scopePendientes($query)
    {
        return $query->where('estatus', 'pendiente');
    }

    /**
     * Scope para candidatos completos
     */
    public function scopeCompletos($query)
    {
        return $query->where('estatus', 'completo');
    }

    /**
     * Scope para candidatos listos para recibir recordatorio
     */
    public function scopeListosParaRecordatorio($query)
    {
        $config = config('candidatos.recordatorios');
        $maxRecordatorios = $config['max_recordatorios'];
        $diasEsperaDefault = $config['dias_entre_envios'];

        return $query->where('estatus', 'pendiente')
            ->where('recordatorios_enviados', '<', $maxRecordatorios)
            ->where(function ($q) {
                $q->whereNull('fecha_inicio')
                  ->orWhere('fecha_inicio', '<=', now());
            })
            ->where(function ($q) use ($diasEsperaDefault) {
                $q->whereNull('ultimo_recordatorio')
                  ->orWhereRaw("ultimo_recordatorio <= DATE_SUB(NOW(), INTERVAL COALESCE(frecuencia_envio, ?) DAY)", [$diasEsperaDefault]);
            });
    }

    /**
     * Obtener requisitos faltantes (pendientes o en proceso)
     */
    public function requisitosFaltantes(): Collection
    {
        return $this->requisitos()
            ->with('tipoRequisito')
            ->whereIn('estado', ['pendiente', 'en_proceso'])
            ->get();
    }

    /**
     * Verificar si puede recibir recordatorio
     */
    public function puedeRecibirRecordatorio(): bool
    {
        $config = config('candidatos.recordatorios');
        
        if (!$config['activo']) {
            return false;
        }

        if ($this->estatus !== 'pendiente') {
            return false;
        }

        if ($this->recordatorios_enviados >= $config['max_recordatorios']) {
            return false;
        }

        // Verificar fecha de inicio
        if ($this->fecha_inicio && $this->fecha_inicio->isFuture()) {
            return false;
        }

        if ($this->ultimo_recordatorio === null) {
            return true;
        }

        $diasEspera = $this->frecuencia_envio ?? $config['dias_entre_envios'];
        $fechaLimite = now()->subDays($diasEspera);

        return $this->ultimo_recordatorio <= $fechaLimite;
    }

    /**
     * Marcar como completo si todos los requisitos están completados
     */
    public function verificarYMarcarCompleto(): bool
    {
        $faltantes = $this->requisitosFaltantes();

        if ($faltantes->isEmpty()) {
            $this->update(['estatus' => 'completo']);
            return true;
        }

        return false;
    }

    /**
     * Registrar envío de recordatorio
     */
    public function registrarRecordatorio(): void
    {
        $this->increment('recordatorios_enviados');
        $this->update(['ultimo_recordatorio' => now()]);
    }

    /**
     * Pausar candidato (alcanzó límite de recordatorios)
     */
    public function pausar(): void
    {
        $this->update(['estatus' => 'pausado']);
    }

    /**
     * Reactivar candidato
     */
    public function reactivar(): void
    {
        $this->update([
            'estatus' => 'pendiente',
            'recordatorios_enviados' => 0,
            'ultimo_recordatorio' => null,
        ]);
    }

    /**
     * Inicializar requisitos según el tipo de candidato
     */
    public function inicializarRequisitos(): void
    {
        $tiposRequisito = $this->tipoCandidato->requisitosObligatorios();

        foreach ($tiposRequisito as $tipoRequisito) {
            RequisitoCandidato::firstOrCreate([
                'candidato_id' => $this->id,
                'tipo_requisito_id' => $tipoRequisito->id,
            ]);
        }
    }

    /**
     * Obtener nombre de la entidad (empresa o contacto)
     */
    public function getNombreEntidadAttribute(): string
    {
        if ($this->empresa) {
            return $this->empresa->razon_social;
        }

        if ($this->empresaExterna) {
            return $this->empresaExterna->razon_social;
        }

        return $this->nombre_contacto;
    }
}
