<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Curso del catálogo que se ofrece a quien terminó otro curso
 * (p. ej. "Claude Code" → "Claude Code Avanzado").
 *
 * El origen se identifica por `claveCurso()` del nombre, no por id: así casan las
 * encuestas del Form, del aula, el legacy y las copias por tutor en Moodle.
 */
class CursoSiguiente extends Model
{
    protected $table = 'cursos_siguientes';

    protected $fillable = [
        'curso_origen_clave',
        'curso_origen_nombre',
        'moodle_curso_id',
        'accion_formativa_id',
        'prioridad',
        'activo',
        'sugerido_auto',
        'confirmado_por',
        'confirmado_en',
    ];

    protected $casts = [
        'prioridad'     => 'integer',
        'activo'        => 'boolean',
        'sugerido_auto' => 'boolean',
        'confirmado_en' => 'datetime',
    ];

    public function cursoDestino(): BelongsTo
    {
        return $this->belongsTo(MoodleCurso::class, 'moodle_curso_id');
    }

    /** Destino alternativo: acción formativa FUNDAE (curso que se imparte de verdad). */
    public function accionFormativa(): BelongsTo
    {
        return $this->belongsTo(AccionFormativa::class, 'accion_formativa_id');
    }

    /** Nombre del curso que se ofrece, venga del catálogo web o de una acción formativa. */
    public function getDestinoTituloAttribute(): ?string
    {
        if ($this->accionFormativa) {
            return $this->accionFormativa->denominacion_limpia;
        }

        return $this->cursoDestino?->titulo;
    }

    /** 'accion' (acción formativa FUNDAE) | 'web' (catálogo webcurso.es) */
    public function getDestinoTipoAttribute(): string
    {
        return $this->accion_formativa_id ? 'accion' : 'web';
    }

    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /** Propuestas automáticas que la usuaria aún no ha confirmado. */
    public function scopePendientesDeConfirmar($query)
    {
        return $query->where('activo', false)->where('sugerido_auto', true)->whereNull('confirmado_en');
    }

    /**
     * Clave de comparación de un nombre de curso.
     *
     * Minúsculas sin tildes, sin marcas "(REPASO)"/"(Desactualizado)", sin el
     * prefijo "Curso de" y sin la coletilla de horas/plataforma ("60h m"), que es
     * lo que diferencia la misma formación entre fuentes. El nivel ("Nivel 2",
     * "avanzado") SÍ se conserva: distingue cursos distintos.
     */
    public static function claveCurso(?string $nombre): string
    {
        $s = mb_strtolower(trim((string) $nombre));
        if ($s === '') {
            return '';
        }

        $s = strtr($s, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c', 'º' => '', 'ª' => '',
        ]);

        $s = preg_replace('/\((repaso|desactualizado)\)/u', ' ', $s);
        // Copia del aula de un tutor: "… Prof. David Guerra"
        $s = preg_replace('/\bprof(\.|esor|esora)?\s+.*$/u', ' ', $s);
        // Horas con letra de plataforma opcional: "60h m", "60 h", "60 horas", "30hb m"
        $s = preg_replace('/\b\d+\s*(h|hb|horas)\b(\s+[am]\b)?/u', ' ', $s);
        $s = preg_replace('/^(curso\s+online\s+de|curso\s+de|curso)\s+/u', '', trim($s));
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s);

        return trim(preg_replace('/\s+/', ' ', $s));
    }
}
