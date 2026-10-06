<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Respuesta del cuestionario de calidad FUNDAE (Microsoft Form) de un alumno.
 * Ver migración create_encuestas_calidad_table y EncuestaCalidadService.
 */
class EncuestaCalidad extends Model
{
    protected $table = 'encuestas_calidad';

    protected $fillable = [
        'forms_id',
        'origen',
        'hora_inicio',
        'hora_fin',
        'fecha_cumplimentacion',
        'alumno_nombre',
        'alumno_email',
        'cif_empresa',
        'numero_accion',
        'numero_grupo',
        'denominacion_accion',
        'curso_resuelto',
        'curso_tipo',
        'curso_fecha_inicio',
        'curso_fecha_fin',
        'curso_origen',
        'modalidad',
        'edad_raw',
        'sexo',
        'titulacion',
        'lugar_trabajo',
        'categoria_profesional',
        'horario_curso',
        'porcentaje_jornada',
        'tamano_empresa',
        'item_01', 'item_02', 'item_03', 'item_04', 'item_05', 'item_06',
        'item_07', 'item_08', 'item_09', 'item_10', 'item_11', 'item_12',
        'item_13', 'item_14', 'item_15', 'item_16', 'item_17', 'item_18', 'item_19',
        // Ítems que solo trae el cuestionario íntegro del aula (plugin Moodle).
        'item_20', 'item_21', 'item_22',
        // Dicotómicos (1 = Sí, 2 = No). Nunca se promedian: ver config sino.
        'sino_pruebas_evaluacion',
        'sino_acreditacion',
        'sino_recomendaria',
        'satisfaccion_general',
        'observaciones',
        'alumno_id',
        'grupo_formativo_id',
        'accion_formativa_id',
        'tutor_id',
        'tutor_label',
        'imported_at',
        'moodle_response_id',
        'moodle_course_id',
        'moodle_user_id',
        'moodle_cmid',
        'moodle_timemodified',
        'oportunidad_estado',
        'oportunidad_nota',
        'oportunidad_por',
        'oportunidad_en',
        'resena_autorizada',
        'resena_nombre_publico',
        'resena_consentimiento_en',
        'resena_consentimiento_version',
    ];

    protected $casts = [
        'hora_inicio'           => 'datetime',
        'hora_fin'              => 'datetime',
        'fecha_cumplimentacion' => 'date',
        'curso_fecha_inicio'    => 'date',
        'curso_fecha_fin'       => 'date',
        'imported_at'           => 'datetime',
        'numero_accion'         => 'integer',
        'satisfaccion_general'  => 'integer',
        'item_01' => 'integer', 'item_02' => 'integer', 'item_03' => 'integer',
        'item_04' => 'integer', 'item_05' => 'integer', 'item_06' => 'integer',
        'item_07' => 'integer', 'item_08' => 'integer', 'item_09' => 'integer',
        'item_10' => 'integer', 'item_11' => 'integer', 'item_12' => 'integer',
        'item_13' => 'integer', 'item_14' => 'integer', 'item_15' => 'integer',
        'item_16' => 'integer', 'item_17' => 'integer', 'item_18' => 'integer',
        'item_19' => 'integer', 'item_20' => 'integer', 'item_21' => 'integer',
        'item_22' => 'integer',
        'sino_pruebas_evaluacion' => 'integer',
        'sino_acreditacion'       => 'integer',
        'sino_recomendaria'       => 'integer',
        'moodle_response_id'      => 'integer',
        'moodle_course_id'        => 'integer',
        'moodle_user_id'          => 'integer',
        'moodle_cmid'             => 'integer',
        'moodle_timemodified'     => 'integer',
        'oportunidad_en'          => 'datetime',
        'resena_autorizada'       => 'boolean',
        'resena_consentimiento_en' => 'datetime',
    ];

    // ─── Relaciones ───

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function grupoFormativo(): BelongsTo
    {
        return $this->belongsTo(GrupoFormativo::class, 'grupo_formativo_id');
    }

    public function accionFormativa(): BelongsTo
    {
        return $this->belongsTo(AccionFormativa::class, 'accion_formativa_id');
    }

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Tutor::class, 'tutor_id');
    }

    public function oportunidadPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oportunidad_por');
    }

    // ─── Scopes ───

    /** Respuestas con satisfacción general alta (>= umbral configurable). */
    public function scopeAltaPuntuacion($query, ?int $umbral = null)
    {
        $umbral ??= (int) config('encuesta_calidad.umbral_alta', 4);
        return $query->where('satisfaccion_general', '>=', $umbral);
    }

    /** Respuestas con satisfacción general baja (<= 2). */
    public function scopeBajaPuntuacion($query, int $umbral = 2)
    {
        return $query->whereNotNull('satisfaccion_general')
            ->where('satisfaccion_general', '<=', $umbral);
    }

    /** Respuestas que traen texto en observaciones. */
    public function scopeConObservacion($query)
    {
        return $query->whereNotNull('observaciones')->where('observaciones', '!=', '');
    }

    /** Respuestas cuya fecha de cumplimentación cae en el año dado. */
    public function scopeDelAno($query, int $anio)
    {
        return $query->whereYear('fecha_cumplimentacion', $anio);
    }

    /**
     * Respuestas que contestaron el ítem 10.1 (¿recomendaría el curso?).
     *
     * Solo lo traen las del cuestionario íntegro del aula; las del Form tienen
     * NULL. Sirve de denominador del KPI: sin este filtro, un "0 %" sobre una
     * vista histórica sería una cifra falsa.
     */
    public function scopeResenaAutorizada($query)
    {
        return $query->where('resena_autorizada', true);
    }

    public function scopeConRecomendacion($query)
    {
        return $query->whereIn('sino_recomendaria', [1, 2]);
    }

    /**
     * Respuestas que traen el cuestionario oficial FUNDAE íntegro.
     *
     * Define la cohorte sobre la que se pueden promediar juntos los ítems base y
     * los añadidos. Se declara por origen y no por "item_20 no nulo" porque un
     * "no contesta" se guarda como NULL y expulsaría a esa respuesta de su propia
     * cohorte, falseando el n.
     */
    public function scopeCuestionarioCompleto($query)
    {
        return $query->whereIn('origen', (array) config(
            'encuesta_calidad.origenes_cuestionario_completo',
            ['moodle_plugin']
        ));
    }
}
