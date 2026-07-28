<?php

namespace App\Contracts;

use App\Models\AccionFormativa;
use App\Models\Tutor;

/**
 * Origen de una matrícula en Moodle susceptible de seguimiento académico.
 *
 * Reportes Moodle nació atado al pivot `grupo_formativo_alumno` (bonificados FUNDAE),
 * lo que dejaba fuera a los autónomos 2x1 y a los particulares. Este contrato normaliza
 * lo que el snapshot y los avisos necesitan saber, de modo que ambos caminos —grupo
 * formativo y matrícula individual— se traten igual sin ramificar en cada comando.
 *
 * Implementado por GrupoFormativoAlumno (bonificado) y MatriculaAutonoma (individual).
 */
interface OrigenMatriculaMoodle
{
    /**
     * Clave con la que se identifica el snapshot de este origen.
     *
     * @return array{grupo_formativo_alumno_id?: int, matricula_autonoma_id?: int}
     */
    public function claveSnapshot(): array;

    /**
     * Discriminador estable sin nulos: "grupo:37" / "autonoma:12".
     * Se usa donde un UNIQUE con columnas nullable dejaría de proteger.
     */
    public function claveOrigen(): string;

    public function alumnoIdMatricula(): ?int;

    public function moodleUserIdMatricula(): ?int;

    public function moodleCourseIdMatricula(): ?int;

    public function fechaInicioCurso(): ?\DateTimeInterface;

    public function fechaFinCurso(): ?\DateTimeInterface;

    public function tutorMatricula(): ?Tutor;

    public function accionFormativaMatricula(): ?AccionFormativa;

    /**
     * Código visible del grupo ("241/3"). Las matrículas individuales no tienen grupo.
     */
    public function codigoGrupoMatricula(): ?string;

    /**
     * Etiqueta corta del tipo de matrícula para la UI: bonificado | autonomo | particular.
     */
    public function tipoMatricula(): string;
}
