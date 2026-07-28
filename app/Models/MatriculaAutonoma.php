<?php

namespace App\Models;

use App\Contracts\OrigenMatriculaMoodle;
use App\Support\MoodlePassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Moodle\Services\MoodleService;

/**
 * Matrícula individual, sin grupo formativo FUNDAE. Dos modalidades:
 *  - autonomo_2x1: curso gratis que acompaña a un bonificado
 *  - particular:   el alumno paga el curso; no pertenece a ninguna empresa cliente
 *
 * En ambos casos el alumno estudia en nuestra aula, así que entra en Reportes Moodle
 * igual que un bonificado (ver OrigenMatriculaMoodle).
 */
class MatriculaAutonoma extends Model implements OrigenMatriculaMoodle
{
    public const MODALIDAD_AUTONOMO = 'autonomo_2x1';
    public const MODALIDAD_PARTICULAR = 'particular';

    protected $table = 'matriculas_autonomas';

    protected $fillable = [
        'candidato_id',
        'modalidad',
        'alumno_id',
        'accion_formativa_id',
        'tutor_id',
        'empresa_id',
        'fecha_inicio',
        'fecha_fin',
        'moodle_course_id',
        'moodle_user_id',
        'moodle_username',
        'estado',
        'intentos_moodle',
        'ultimo_error_moodle',
    ];

    protected $casts = [
        'fecha_inicio'     => 'date',
        'fecha_fin'        => 'date',
        'moodle_course_id' => 'integer',
        'moodle_user_id'   => 'integer',
        'intentos_moodle'  => 'integer',
    ];

    // ─── Relaciones ───

    public function candidato(): BelongsTo
    {
        return $this->belongsTo(Candidato::class, 'candidato_id');
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
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

    // ─── Scopes ───

    public function scopeAutonomos($query)
    {
        return $query->where('modalidad', self::MODALIDAD_AUTONOMO);
    }

    public function scopeParticulares($query)
    {
        return $query->where('modalidad', self::MODALIDAD_PARTICULAR);
    }

    public function esParticular(): bool
    {
        return $this->modalidad === self::MODALIDAD_PARTICULAR;
    }

    // ─── OrigenMatriculaMoodle: los datos del curso están en la propia fila ───

    public function claveSnapshot(): array
    {
        return ['matricula_autonoma_id' => $this->id];
    }

    public function claveOrigen(): string
    {
        return 'autonoma:' . $this->id;
    }

    public function alumnoIdMatricula(): ?int
    {
        return $this->alumno_id;
    }

    public function moodleUserIdMatricula(): ?int
    {
        return $this->moodle_user_id;
    }

    public function moodleCourseIdMatricula(): ?int
    {
        return $this->moodle_course_id;
    }

    public function fechaInicioCurso(): ?\DateTimeInterface
    {
        return $this->fecha_inicio;
    }

    public function fechaFinCurso(): ?\DateTimeInterface
    {
        return $this->fecha_fin;
    }

    public function tutorMatricula(): ?Tutor
    {
        return $this->tutor;
    }

    public function accionFormativaMatricula(): ?AccionFormativa
    {
        return $this->accionFormativa;
    }

    public function codigoGrupoMatricula(): ?string
    {
        // Una matrícula individual no pertenece a ningún grupo FUNDAE.
        return null;
    }

    public function tipoMatricula(): string
    {
        return $this->esParticular() ? 'particular' : 'autonomo';
    }

    // ─── Lógica de negocio ───

    /**
     * Ejecutar la matrícula del autónomo en Moodle.
     * Flujo individual: crear/actualizar usuario → matricular → enviar email.
     */
    public function ejecutarEnMoodle(int $moodleCourseId): array
    {
        $moodle = app(MoodleService::class);
        $alumno = $this->alumno;
        $resultados = ['exitos' => 0, 'errores' => 0];

        try {
            $username = $alumno->email ?? "{$alumno->nif}@webcurso.es";
            $password = MoodlePassword::generar($alumno->nombre);

            // Buscar usuario existente o crear
            $existente = $moodle->findUserByUsername($username);

            if ($existente) {
                $moodleUserId = $existente['id'];
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

            // Matricular en curso con fechas (timezone Madrid)
            $timestart = $this->fecha_inicio
                ? \Carbon\Carbon::parse($this->fecha_inicio->format('Y-m-d'), 'Europe/Madrid')->startOfDay()->getTimestamp()
                : null;
            $timeend = $this->fecha_fin
                ? \Carbon\Carbon::parse($this->fecha_fin->format('Y-m-d'), 'Europe/Madrid')->endOfDay()->getTimestamp()
                : null;
            $moodle->enrolInCourse($moodleUserId, $moodleCourseId, roleId: 5, timestart: $timestart, timeend: $timeend);

            // Actualizar registro
            $this->update([
                'moodle_course_id'    => $moodleCourseId,
                'moodle_user_id'      => $moodleUserId,
                'moodle_username'     => $username,
                'estado'              => 'matriculado',
                'ultimo_error_moodle' => null,
            ]);

            // Enviar email de credenciales
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
                    esBonificado: false,
                )
            );

            $resultados['exitos']++;

        } catch (\Exception $e) {
            $this->update([
                'moodle_username'     => $username ?? null,
                'estado'              => 'error',
                'intentos_moodle'     => $this->intentos_moodle + 1,
                'ultimo_error_moodle' => Str::limit($e->getMessage(), 500),
            ]);

            Log::error("Error Moodle matrícula autónoma #{$this->id}, alumno #{$alumno->id}: {$e->getMessage()}");
            $resultados['errores']++;
        }

        return $resultados;
    }
}
