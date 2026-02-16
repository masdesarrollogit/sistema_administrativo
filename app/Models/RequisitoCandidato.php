<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitoCandidato extends Model
{
    use HasFactory;

    protected $table = 'requisitos_candidato';

    protected $fillable = [
        'candidato_id',
        'tipo_requisito_id',
        'estado',
        'fecha_completado',
        'notas',
        'documento_path',
    ];

    protected $casts = [
        'fecha_completado' => 'datetime',
    ];

    /**
     * Relación con candidato
     */
    public function candidato(): BelongsTo
    {
        return $this->belongsTo(Candidato::class, 'candidato_id');
    }

    /**
     * Relación con tipo de requisito
     */
    public function tipoRequisito(): BelongsTo
    {
        return $this->belongsTo(TipoRequisito::class, 'tipo_requisito_id');
    }

    /**
     * Marcar como completado
     */
    public function marcarCompletado(?string $notas = null): void
    {
        $this->update([
            'estado' => 'completado',
            'fecha_completado' => now(),
            'notas' => $notas ?? $this->notas,
        ]);

        // Verificar si el candidato está completo
        $this->candidato->verificarYMarcarCompleto();
    }

    /**
     * Verificar si está completado
     */
    public function estaCompletado(): bool
    {
        return $this->estado === 'completado';
    }

    /**
     * Scope para requisitos pendientes
     */
    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    /**
     * Scope para requisitos completados
     */
    public function scopeCompletados($query)
    {
        return $query->where('estado', 'completado');
    }
}
