<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Support\MoodlePassword;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Moodle\Services\MoodleService;

class GrupoFormativo extends Model
{
    use HasFactory;

    protected $table = 'grupos_formativos';

    protected $fillable = [
        'candidato_id',
        'accion_formativa_id',
        'tutor_id',
        'empresa_id',
        'tramo_horario',
        'descripcion',
        'id_grupo_fundae',
        'moodle_group_id',
        'moodle_course_id',
        'fecha_inicio',
        'fecha_fin',
        'jornada_laboral',
        'estado',
    ];

    protected $casts = [
        'fecha_inicio'    => 'date',
        'fecha_fin'       => 'date',
        'id_grupo_fundae' => 'integer',
        'moodle_group_id' => 'integer',
        'moodle_course_id'=> 'integer',
        'jornada_laboral' => 'integer',
    ];

    // ─── Relaciones ───

    public function candidato(): BelongsTo
    {
        return $this->belongsTo(Candidato::class, 'candidato_id');
    }

    public function accionFormativa(): BelongsTo
    {
        return $this->belongsTo(AccionFormativa::class, 'accion_formativa_id');
    }

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Tutor::class, 'tutor_id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function alumnos(): BelongsToMany
    {
        return $this->belongsToMany(Alumno::class, 'grupo_formativo_alumno')
            ->withPivot([
                'moodle_user_id', 'moodle_username', 'estado_moodle', 'intentos_moodle', 'ultimo_error_moodle',
                'ficha_inscripcion_path', 'ficha_inscripcion_tipo', 'ficha_inscripcion_subida_en',
            ])
            ->withTimestamps();
    }

    // ─── Scopes ───

    public function scopeAbiertos($query)
    {
        return $query->where('estado', 'abierto')
            ->where('fecha_inicio', '>', now()->addDays(2));
    }

    public function scopeEnCurso($query)
    {
        return $query->where('estado', 'en_curso');
    }

    public function scopeCompletados($query)
    {
        return $query->where('estado', 'completado');
    }

    // ─── Atributos ───

    /**
     * Generar descripción del grupo para FUNDAE.
     * Formato 1 alumno: {CIF} {empresa} {nombre_alumno} {curso} {horas}h {tramo}{tutor}
     * Formato N alumnos: {CIF} {empresa} ({N}) {curso} {horas}h {tramo}{tutor}
     */
    public function getDescripcionFundaeAttribute(): string
    {
        $empresa = $this->empresa;
        $accion = $this->accionFormativa;
        $tutor = $this->tutor;
        $tramo = $this->tramo_horario === 'tramo_1' ? 'T1' : 'T2';
        $cantAlumnos = $this->alumnos()->count();

        if ($cantAlumnos === 1) {
            $alumno = $this->alumnos->first();
            $parteAlumno = $alumno->nombre_completo;
        } else {
            $parteAlumno = "({$cantAlumnos})";
        }

        $descripcion = sprintf(
            '%s %s %s %s %dh %s%s',
            $empresa->cif,
            $empresa->razon_social,
            $parteAlumno,
            $accion->denominacion_limpia,
            $accion->horas,
            $tramo,
            $tutor->iniciales,
        );

        return substr($descripcion, 0, 100);
    }

    /**
     * Código del grupo FUNDAE: {accion}/{grupo}
     */
    public function getCodigoFundaeAttribute(): ?string
    {
        if (!$this->id_grupo_fundae) {
            return null;
        }

        return $this->accionFormativa->numero_accion . '/' . $this->id_grupo_fundae;
    }

    public function getTramoLabelAttribute(): string
    {
        return match ($this->tramo_horario) {
            'tramo_1' => 'T1 (8:00-11:00)',
            'tramo_2' => 'T2 (15:00-18:00)',
            default => $this->tramo_horario,
        };
    }

    /**
     * Verificar si el grupo está abierto (hasta 2 días antes del inicio).
     */
    public function estaAbierto(): bool
    {
        return $this->estado === 'abierto'
            && $this->fecha_inicio->startOfDay()->gte(now()->startOfDay()->addDays(2));
    }

    // ─── Lógica de negocio ───

    /**
     * Asignar el siguiente id_grupo_fundae secuencial para esta acción formativa.
     * Consulta tanto los grupos importados de FUNDAE (tabla grupos) como los creados en el Panel.
     */
    public function asignarIdGrupoFundae(): void
    {
        $numeroAccion = $this->accionFormativa->numero_accion;

        // Máximo en la tabla grupos (importados de FUNDAE)
        $maxImportado = \App\Models\Grupo::where('codigo_grupo_accion_formativa', (string) $numeroAccion)
            ->max(\DB::raw('CAST(codigo_grupo AS UNSIGNED)'));

        // Máximo en la tabla grupos_formativos (creados en el Panel)
        $maxPanel = self::where('accion_formativa_id', $this->accion_formativa_id)
            ->whereNotNull('id_grupo_fundae')
            ->max('id_grupo_fundae');

        $siguiente = max((int) $maxImportado, (int) $maxPanel) + 1;

        $this->update(['id_grupo_fundae' => $siguiente]);
    }

    /**
     * Contar alumnos de un tutor en un tramo que coinciden EN EL TIEMPO con un período dado
     * (para el límite FUNDAE de 80 alumnos SIMULTÁNEOS por tutor por tramo).
     *
     * Si se pasan fechas, solo cuentan los grupos cuyo rango se solapa con [fechaInicio, fechaFin]
     * (mismo criterio que Alumno::tieneGrupoActivoEnPeriodo). Así, al pasar la fecha_fin de un
     * grupo este deja de contar automáticamente y se libera el cupo del tramo.
     *
     * Un grupo que solapa parcialmente la ventana se cuenta entero (aproximación conservadora:
     * errar hacia "no exceder" es lo correcto para FUNDAE).
     *
     * Sin fechas, cuenta todos los grupos activos del tramo (comportamiento de respaldo).
     */
    public static function alumnosPorTutorYTramo(
        int $tutorId,
        string $tramo,
        string|\Carbon\Carbon|null $fechaInicio = null,
        string|\Carbon\Carbon|null $fechaFin = null,
        ?int $excluirGrupoId = null
    ): int {
        $query = self::where('tutor_id', $tutorId)
            ->where('tramo_horario', $tramo)
            ->whereIn('estado', ['abierto', 'comunicado', 'en_curso']);

        if ($excluirGrupoId) {
            $query->where('id', '!=', $excluirGrupoId);
        }

        if ($fechaInicio && $fechaFin) {
            // Solapamiento: los rangos se cruzan si inicio_A <= fin_B Y fin_A >= inicio_B.
            $query->where('fecha_inicio', '<=', $fechaFin)
                  ->where('fecha_fin', '>=', $fechaInicio);
        }

        return $query->withCount('alumnos')->get()->sum('alumnos_count');
    }

    /**
     * Ejecutar la matrícula de todos los alumnos del grupo en Moodle.
     * Crea un grupo Moodle con nombre {accion}/{grupo} y añade los alumnos.
     */
    public function ejecutarEnMoodle(int $moodleCourseId): array
    {
        $moodle = app(MoodleService::class);
        $resultados = ['exitos' => 0, 'errores' => 0];

        // Crear (o reutilizar) el grupo Moodle para este grupo formativo
        $moodleGroupId = $this->moodle_group_id;
        if (!$moodleGroupId) {
            $nombreGrupo = $this->codigo_fundae ?? "GF-{$this->id}";
            $moodleGroupId = $moodle->createGroup($moodleCourseId, $nombreGrupo);
            $this->update([
                'moodle_group_id'  => $moodleGroupId,
                'moodle_course_id' => $moodleCourseId,
            ]);
        }

        // Reactivar matrícula del tutor con fecha fin del grupo
        $tutor = $this->tutor;
        if ($tutor?->moodle_username) {
            try {
                $tutorUser = $moodle->findUserByUsername($tutor->moodle_username);
                if ($tutorUser) {
                    $timeend = $this->fecha_fin
                        ? \Carbon\Carbon::parse($this->fecha_fin->format('Y-m-d'), 'Europe/Madrid')->endOfDay()->getTimestamp()
                        : null;
                    $moodle->enrolInCourse($tutorUser['id'], $moodleCourseId, roleId: 3, timestart: null, timeend: $timeend, suspend: 0);
                }
            } catch (\Exception $e) {
                Log::warning("No se pudo reactivar matrícula del tutor #{$tutor->id} en curso #{$moodleCourseId}: {$e->getMessage()}");
            }
        }

        $moodleUserIds = []; // para añadir al grupo al final

        foreach ($this->alumnos as $alumno) {
            $pivot = $alumno->pivot;

            if ($pivot->estado_moodle === 'matriculado') {
                if ($pivot->moodle_user_id) {
                    $moodleUserIds[] = $pivot->moodle_user_id;
                }
                continue;
            }

            try {
                // Username = email del alumno
                $username = $alumno->email ?? "{$alumno->nif}@webcurso.es";

                $password = MoodlePassword::generar($alumno->nombre);

                // Buscar usuario existente o crear
                $existente = $moodle->findUserByUsername($username);

                if ($existente) {
                    $moodleUserId = $existente['id'];
                    // Actualizar contraseña con el patrón establecido
                    $moodle->updateUserPassword($moodleUserId, $password);
                } else {
                    $result = $moodle->createUser([
                        'username'  => $username,
                        'password'  => $password,
                        'firstname' => explode(' ', trim($alumno->nombre))[0],
                        'lastname'  => trim("{$alumno->apellido1} {$alumno->apellido2}"),
                        'email'     => $username,
                    ]);
                    $moodleUserId = $result['id'];
                }

                // Matricular en curso con fechas exactas del grupo (timezone Madrid)
                $timestart = $this->fecha_inicio
                    ? \Carbon\Carbon::parse($this->fecha_inicio->format('Y-m-d'), 'Europe/Madrid')->startOfDay()->getTimestamp()
                    : null;
                $timeend = $this->fecha_fin
                    ? \Carbon\Carbon::parse($this->fecha_fin->format('Y-m-d'), 'Europe/Madrid')->endOfDay()->getTimestamp()
                    : null;
                $moodle->enrolInCourse($moodleUserId, $moodleCourseId, roleId: 5, timestart: $timestart, timeend: $timeend);
                $moodleUserIds[] = $moodleUserId;

                // Actualizar pivot
                $this->alumnos()->updateExistingPivot($alumno->id, [
                    'moodle_user_id'      => $moodleUserId,
                    'moodle_username'     => $username,
                    'estado_moodle'       => 'matriculado',
                    'ultimo_error_moodle' => null,
                ]);

                // Enviar email de credenciales siempre (usuario nuevo o existente)
                $accion = $this->accionFormativa;
                Mail::mailer('moodle')->to($username)->send(
                    new \App\Mail\CredencialesMoodleMail(
                        alumno: $alumno,
                        username: $username,
                        password: $password,
                        moodleCourseId: $moodleCourseId,
                        cursoNombre: $accion->denominacion_limpia,
                        cursoHoras: $accion->horas,
                        fechaInicio: $this->fecha_inicio,
                        fechaFin: $this->fecha_fin,
                    )
                );

                $resultados['exitos']++;

            } catch (\Exception $e) {
                $this->alumnos()->updateExistingPivot($alumno->id, [
                    'moodle_username'     => $username ?? null,
                    'estado_moodle'       => 'error',
                    'intentos_moodle'     => ($pivot->intentos_moodle ?? 0) + 1,
                    'ultimo_error_moodle' => Str::limit($e->getMessage(), 500),
                ]);

                Log::error("Error Moodle grupo #{$this->id}, alumno #{$alumno->id}: {$e->getMessage()}");
                $resultados['errores']++;
            }
        }

        // Añadir todos los alumnos matriculados al grupo Moodle
        if (!empty($moodleUserIds)) {
            try {
                $moodle->addUsersToGroup($moodleGroupId, $moodleUserIds);
            } catch (\Exception $e) {
                Log::warning("No se pudo añadir alumnos al grupo Moodle #{$moodleGroupId}: {$e->getMessage()}");
            }
        }

        // Pasar a en_curso solo si NO quedan alumnos pendientes o con error
        $hayPendientes = $this->alumnos()->wherePivotNotIn('estado_moodle', ['matriculado', 'aulasystem'])->exists();
        if (!$hayPendientes && $resultados['exitos'] > 0) {
            $this->update(['estado' => 'en_curso']);
        }

        // Notificar al admin si hubo errores
        if ($resultados['errores'] > 0) {
            $adminEmail = config('candidatos.contacto.email');
            if ($adminEmail) {
                Mail::raw(
                    "Grupo #{$this->id}: {$resultados['exitos']} matriculados, {$resultados['errores']} errores.\nRevisa el Panel.",
                    fn($m) => $m->to($adminEmail)->subject('Errores de matrícula Moodle - Panel WebCurso')
                );
            }
        }

        return $resultados;
    }

}
