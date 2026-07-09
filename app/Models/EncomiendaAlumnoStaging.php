<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alumno en staging proveniente del sistema externo de encomienda. Sus campos
 * ya vienen mapeados a códigos FUNDAE. Se materializa como Alumno real al
 * añadirlo a un Grupo Formativo desde MatriculacionPanel.
 */
class EncomiendaAlumnoStaging extends Model
{
    protected $table = 'encomienda_alumnos_staging';

    protected $fillable = [
        'source_id',
        'encomienda_contrato_id',
        'contrato_source_id',
        'referencia_contrato',
        'nombre',
        'apellido1',
        'apellido2',
        'nif',
        'email',
        'telefono',
        'niss',
        'grupo_cotizacion_tgss',
        'fecha_nacimiento',
        'sexo',
        'nivel_estudios',
        'categoria_profesional',
        'cargo',
        'curso_interes',
        'horas',
        'fecha_prevista_inicio',
        'comentarios',
        'alumno_id',
        'estado',
        'sincronizado_en',
    ];

    protected $casts = [
        'fecha_nacimiento'      => 'date',
        'nivel_estudios'        => 'integer',
        'categoria_profesional' => 'integer',
        'sincronizado_en'       => 'datetime',
    ];

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(EncomiendaContrato::class, 'encomienda_contrato_id');
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombre} {$this->apellido1} {$this->apellido2}");
    }

    /** Campos listos para crear/actualizar un Alumno real (updateOrCreate). */
    public function datosParaAlumno(int $empresaId): array
    {
        return [
            'empresa_id'            => $empresaId,
            'nombre'                => $this->nombre,
            'apellido1'             => $this->apellido1,
            'apellido2'             => $this->apellido2,
            'email'                 => $this->email,
            'telefono'              => $this->telefono,
            'niss'                  => $this->niss,
            'grupo_cotizacion_tgss' => $this->grupo_cotizacion_tgss,
            'fecha_nacimiento'      => $this->fecha_nacimiento,
            'sexo'                  => $this->sexo,
            'nivel_estudios'        => $this->nivel_estudios,
            'categoria_profesional' => $this->categoria_profesional,
        ];
    }
}
