<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditarEnriquecimientoLegacy extends Command
{
    protected $signature = 'alumnos:auditar-enriquecimiento-legacy
        {--year= : Filtrar por año (fecha_inicio o fecha_fin tocan ese año). Sin filtro audita todo el histórico}
        {--csv= : Ruta opcional para exportar CSV. Sin --year exporta solo rescatables (escalones 2 y 3); con --year exporta TODOS los cursos sin acción del año}';

    protected $description = 'Audita por qué los cursos legacy quedaron sin acción/grupo. Clasifica los fallos del matching de `alumnos:enriquecer-cursos-legacy` y opcionalmente exporta un CSV. NO modifica datos.';

    private ?int $year = null;

    public function handle(): int
    {
        $yearOpt = $this->option('year');
        if ($yearOpt !== null && $yearOpt !== '') {
            if (!ctype_digit((string) $yearOpt) || (int) $yearOpt < 2000 || (int) $yearOpt > 2100) {
                $this->error("--year inválido: '{$yearOpt}'. Debe ser un año entre 2000 y 2100.");
                return self::FAILURE;
            }
            $this->year = (int) $yearOpt;
        }

        $this->info('=== Auditoría enriquecimiento legacy ===');
        if ($this->year !== null) {
            $this->line("Filtro: cursos que tocan el año {$this->year}");
        }
        $this->newLine();

        $this->seccionUniverso();
        $clasificacion = $this->seccionEstrategia1();
        $this->seccionEstrategia2();
        $this->seccionAnomalias();

        $csvPath = $this->option('csv');
        if ($csvPath) {
            $this->newLine();
            if ($this->year !== null) {
                $this->exportarCsvAnio($csvPath, $clasificacion);
            } else {
                $this->exportarCsv($csvPath, $clasificacion);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Devuelve el fragmento SQL del filtro de año (con AND inicial) o cadena vacía.
     * Aplica sobre alias `c` de alumnos_legacy_cursos.
     */
    private function filtroAnio(string $alias = 'c'): string
    {
        if ($this->year === null) {
            return '';
        }
        $ini = "{$this->year}-01-01";
        $fin = "{$this->year}-12-31";
        return " AND ({$alias}.fecha_inicio <= '{$fin}' AND {$alias}.fecha_fin >= '{$ini}')";
    }

    private function seccionUniverso(): void
    {
        $this->info('--- A. Universo ---');

        $f = $this->filtroAnio('c');
        // Para subqueries que no alias `c`, generamos un filtro alterno sobre alumnos_legacy_cursos directo
        $fDirect = $this->year !== null
            ? " AND (fecha_inicio <= '{$this->year}-12-31' AND fecha_fin >= '{$this->year}-01-01')"
            : '';

        $rows = DB::select("
            SELECT
                (SELECT COUNT(*) FROM alumnos_legacy_cursos WHERE 1=1 {$fDirect}) AS total,
                (SELECT COUNT(*) FROM alumnos_legacy_cursos WHERE origen_enriquecimiento='grupos_fundae' {$fDirect}) AS enr_grupos,
                (SELECT COUNT(*) FROM alumnos_legacy_cursos WHERE origen_enriquecimiento='participantes_bonificados' {$fDirect}) AS enr_part,
                (SELECT COUNT(*) FROM alumnos_legacy_cursos WHERE (formation_group_alpha IS NULL OR formation_group_alpha='') {$fDirect}) AS sin_enr,
                (SELECT COUNT(*) FROM alumnos_legacy_cursos c
                   INNER JOIN alumnos a ON a.nif=c.nif
                   WHERE (c.formation_group_alpha IS NULL OR c.formation_group_alpha='') {$f}) AS sin_enr_con_alumno
        ");
        $r = $rows[0];

        $sinEnrSinAlumno = $r->sin_enr - $r->sin_enr_con_alumno;

        $this->table(['Métrica', 'Valor'], [
            ['Total cursos legacy', $r->total],
            ['Enriquecidos via tabla `grupos` (FUNDAE)', $r->enr_grupos],
            ['Enriquecidos via `participantes_bonificados`', $r->enr_part],
            ['Sin enriquecer (acción NULL/vacía)', $r->sin_enr],
            ['  └─ con alumno vinculado en `alumnos`', $r->sin_enr_con_alumno],
            ['  └─ sin alumno vinculado en `alumnos`', $sinEnrSinAlumno],
        ]);
        $this->newLine();
    }

    /**
     * Para cada curso sin enriquecer con alumno vinculado, intenta un JOIN
     * progresivamente más laxo y clasifica en el escalón MÁS ESTRICTO que matchea.
     *
     * Devuelve el array de clasificación (curso_id => ['escalon' => N, 'candidato' => ?stdClass])
     * para reutilizar en el CSV.
     */
    private function seccionEstrategia1(): array
    {
        $this->info('--- B. Estrategia 1 (tabla `grupos` FUNDAE) — escalones ---');

        $f = $this->filtroAnio('c');

        // Universo: cursos sin enriquecer con alumno vinculado. Incluimos los flags por escalón
        // y un candidato "best-effort" (primero por g.id) para CSV. Si hay >1 candidato en el
        // escalón 4 lo marcamos como ambiguo.
        $rows = DB::select("
            SELECT
                c.id AS curso_id,
                c.nif,
                c.curso_titulo,
                c.fecha_inicio AS fi_legacy,
                c.fecha_fin    AS ff_legacy,
                a.nombre       AS alumno_nombre,
                a.apellido1    AS alumno_apellido1,
                e.cif          AS empresa_cif,
                SUBSTRING_INDEX(TRIM(a.nombre), ' ', 1) AS primer_nombre,

                -- Escalón 1: actual (CIF + fi + ff + LIKE nombre completo)
                EXISTS (
                    SELECT 1 FROM grupos g
                    WHERE g.cif = e.cif
                      AND g.inicio = c.fecha_inicio
                      AND g.fin = c.fecha_fin
                      AND g.denominacion LIKE CONCAT('%', a.nombre, '%')
                ) AS m1,

                -- Escalón 2: CIF + fi + ff + LIKE primer_nombre
                EXISTS (
                    SELECT 1 FROM grupos g
                    WHERE g.cif = e.cif
                      AND g.inicio = c.fecha_inicio
                      AND g.fin = c.fecha_fin
                      AND g.denominacion LIKE CONCAT('%', SUBSTRING_INDEX(TRIM(a.nombre), ' ', 1), '%')
                ) AS m2,

                -- Escalón 3: CIF + fi + LIKE primer_nombre (relaja fecha_fin)
                EXISTS (
                    SELECT 1 FROM grupos g
                    WHERE g.cif = e.cif
                      AND g.inicio = c.fecha_inicio
                      AND g.denominacion LIKE CONCAT('%', SUBSTRING_INDEX(TRIM(a.nombre), ' ', 1), '%')
                ) AS m3,

                -- Escalón 4-5: CIF + fi (sin nombre); contamos candidatos para decidir 4 vs 5
                (SELECT COUNT(*) FROM grupos g
                    WHERE g.cif = e.cif AND g.inicio = c.fecha_inicio) AS n_cif_fi

            FROM alumnos_legacy_cursos c
            INNER JOIN alumnos a ON a.nif = c.nif
            INNER JOIN empresas e ON e.id = a.empresa_id
            WHERE (c.formation_group_alpha IS NULL OR c.formation_group_alpha = '')
              {$this->filtroAnio('c')}
        ");

        $clasificacion = [];
        $conteo = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        foreach ($rows as $r) {
            if ($r->m1) {
                $escalon = 1;
            } elseif ($r->m2) {
                $escalon = 2;
            } elseif ($r->m3) {
                $escalon = 3;
            } elseif ((int) $r->n_cif_fi === 1) {
                $escalon = 4;
            } elseif ((int) $r->n_cif_fi > 1) {
                $escalon = 5;
            } else {
                $escalon = 6;
            }
            $conteo[$escalon]++;
            $clasificacion[$r->curso_id] = ['escalon' => $escalon, 'row' => $r];
        }

        $total = array_sum($conteo);

        $this->table(['Escalón', 'Descripción', 'Cursos'], [
            ['1', 'Matchea con la lógica actual (CIF+fi+ff+LIKE nombre completo)', $conteo[1]],
            ['2', 'Matchearía relajando solo el LIKE a primer nombre', $conteo[2]],
            ['3', 'Matchearía relajando LIKE + fecha_fin (caso Martina)', $conteo[3]],
            ['4', 'Solo CIF+fecha_inicio coincide, candidato único', $conteo[4]],
            ['5', 'Solo CIF+fecha_inicio coincide, múltiples candidatos (ambiguo)', $conteo[5]],
            ['6', 'Sin candidato en `grupos` por CIF+fecha_inicio', $conteo[6]],
            ['', 'TOTAL universo (sin enriquecer + con alumno)', $total],
        ]);
        $this->newLine();

        return $clasificacion;
    }

    private function seccionEstrategia2(): void
    {
        $this->info('--- C. Estrategia 2 (`participantes_bonificados`) ---');

        $f = $this->filtroAnio('c');

        $rows = DB::select("
            SELECT
                (SELECT COUNT(DISTINCT c.id)
                    FROM alumnos_legacy_cursos c
                    INNER JOIN participantes_bonificados pb
                        ON pb.nif_participante=c.nif
                       AND pb.fecha_inicio=c.fecha_inicio
                       AND pb.fecha_fin=c.fecha_fin
                       AND pb.id_codigo_grupo IS NOT NULL AND pb.id_codigo_grupo!=''
                    WHERE (c.formation_group_alpha IS NULL OR c.formation_group_alpha='') {$f}
                ) AS estricto,

                (SELECT COUNT(DISTINCT c.id)
                    FROM alumnos_legacy_cursos c
                    INNER JOIN participantes_bonificados pb
                        ON pb.nif_participante=c.nif
                       AND pb.fecha_inicio=c.fecha_inicio
                       AND pb.id_codigo_grupo IS NOT NULL AND pb.id_codigo_grupo!=''
                    WHERE (c.formation_group_alpha IS NULL OR c.formation_group_alpha='') {$f}
                ) AS relajado_ff,

                (SELECT COUNT(DISTINCT c.id)
                    FROM alumnos_legacy_cursos c
                    INNER JOIN participantes_bonificados pb ON pb.nif_participante=c.nif
                    WHERE (c.formation_group_alpha IS NULL OR c.formation_group_alpha='') {$f}
                ) AS solo_nif
        ");
        $r = $rows[0];

        $this->table(['Match', 'Cursos'], [
            ['NIF + fecha_inicio + fecha_fin (estricto, igual al cron)', $r->estricto],
            ['NIF + fecha_inicio (relajando fecha_fin)', $r->relajado_ff],
            ['Solo NIF (cualquier fecha)', $r->solo_nif],
        ]);
        $this->newLine();
    }

    private function seccionAnomalias(): void
    {
        $this->info('--- D. Anomalías ---');

        $f = $this->filtroAnio('c');
        $fDirect = $this->year !== null
            ? " AND (fecha_inicio <= '{$this->year}-12-31' AND fecha_fin >= '{$this->year}-01-01')"
            : '';

        $fechaInvertida = DB::select("
            SELECT c.id, c.nif, c.curso_titulo, c.fecha_inicio, c.fecha_fin,
                   a.nombre, a.apellido1
            FROM alumnos_legacy_cursos c
            LEFT JOIN alumnos a ON a.nif=c.nif
            WHERE c.fecha_fin < c.fecha_inicio
              {$f}
            ORDER BY c.id
        ");

        $this->line('Registros con `fecha_fin < fecha_inicio` (bug fecha en legacy): ' . count($fechaInvertida));
        if (count($fechaInvertida) > 0) {
            $this->table(
                ['curso_id', 'nif', 'alumno', 'curso', 'fecha_inicio', 'fecha_fin'],
                array_map(fn ($r) => [
                    $r->id,
                    $r->nif,
                    trim(($r->nombre ?? '') . ' ' . ($r->apellido1 ?? '')) ?: '(sin alumno)',
                    $r->curso_titulo,
                    $r->fecha_inicio,
                    $r->fecha_fin,
                ], $fechaInvertida)
            );
        }

        $fechasNulas = DB::scalar("
            SELECT COUNT(*) FROM alumnos_legacy_cursos
            WHERE (fecha_inicio IS NULL OR fecha_fin IS NULL) {$fDirect}
        ");
        $this->line("Registros con fecha_inicio o fecha_fin NULL: {$fechasNulas}");

        $alumnoSinNombre = DB::scalar("
            SELECT COUNT(*) FROM alumnos_legacy_cursos c
            INNER JOIN alumnos a ON a.nif=c.nif
            WHERE (c.formation_group_alpha IS NULL OR c.formation_group_alpha='')
              AND (a.nombre IS NULL OR a.nombre='')
              {$f}
        ");
        $this->line("Cursos sin enriquecer cuyo alumno tiene nombre vacío: {$alumnoSinNombre}");
        $this->newLine();
    }

    /**
     * Exporta CSV completo de cursos sin acción del año filtrado (todos los escalones,
     * incluso sin alumno vinculado). Incluye candidato cuando lo hay y la columna
     * tiene_alumno_vinculado para distinguir los accionables de los huérfanos.
     */
    private function exportarCsvAnio(string $path, array $clasificacion): void
    {
        $absPath = $this->resolverRutaCsv($path);
        $dir = dirname($absPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $fh = fopen($absPath, 'w');
        fputcsv($fh, [
            'tiene_alumno_vinculado', 'escalon', 'curso_legacy_id', 'nif',
            'alumno_nombre', 'alumno_apellido1', 'empresa_cif', 'empresa_razon_social',
            'curso_titulo', 'fecha_inicio_legacy', 'fecha_fin_legacy',
            'candidato_grupo_id', 'candidato_inicio', 'candidato_fin',
            'candidato_denominacion', 'candidato_accion', 'candidato_codigo_grupo',
            'n_candidatos',
        ]);

        $emitidos = 0;

        // Vinculados — usar la clasificación ya calculada
        foreach ($clasificacion as $cursoId => $c) {
            $r = $c['row'];
            $candidato = $this->mejorCandidatoFlexible($r, $c['escalon']);

            fputcsv($fh, [
                1,
                $c['escalon'],
                $cursoId,
                $r->nif,
                $r->alumno_nombre,
                $r->alumno_apellido1,
                $r->empresa_cif,
                $this->razonSocialDeCif($r->empresa_cif),
                $r->curso_titulo,
                $r->fi_legacy,
                $r->ff_legacy,
                $candidato['g_id'] ?? '',
                $candidato['inicio'] ?? '',
                $candidato['fin'] ?? '',
                $candidato['denominacion'] ?? '',
                $candidato['accion'] ?? '',
                $candidato['codigo_grupo'] ?? '',
                $candidato['n'] ?? 0,
            ]);
            $emitidos++;
        }

        // No vinculados — emitir con datos del legacy y candidatos (si los hay) por legacy_cif_resuelto
        $fDirect = $this->year !== null
            ? " AND (c.fecha_inicio <= '{$this->year}-12-31' AND c.fecha_fin >= '{$this->year}-01-01')"
            : '';

        $huerfanos = DB::select("
            SELECT c.id AS curso_id, c.nif, c.curso_titulo,
                   c.fecha_inicio AS fi, c.fecha_fin AS ff,
                   c.legacy_cif_resuelto AS cif,
                   c.legacy_company_text AS razon_social
            FROM alumnos_legacy_cursos c
            LEFT JOIN alumnos a ON a.nif = c.nif
            WHERE (c.formation_group_alpha IS NULL OR c.formation_group_alpha = '')
              AND a.id IS NULL
              {$fDirect}
            ORDER BY c.id
        ");

        foreach ($huerfanos as $h) {
            $candidato = $h->cif
                ? $this->candidatoPorCifFecha((string) $h->cif, (string) $h->fi)
                : [];

            fputcsv($fh, [
                0,
                '-', // sin escalón porque no hay alumno
                $h->curso_id,
                $h->nif,
                '', // sin alumno → sin nombre
                '',
                $h->cif,
                $h->razon_social,
                $h->curso_titulo,
                $h->fi,
                $h->ff,
                $candidato['g_id'] ?? '',
                $candidato['inicio'] ?? '',
                $candidato['fin'] ?? '',
                $candidato['denominacion'] ?? '',
                $candidato['accion'] ?? '',
                $candidato['codigo_grupo'] ?? '',
                $candidato['n'] ?? 0,
            ]);
            $emitidos++;
        }

        fclose($fh);

        $this->info("CSV escrito: {$absPath} ({$emitidos} filas)");
    }

    /**
     * Igual que mejorCandidato pero también atiende escalones 4 y 5 (candidato por CIF+fi sin nombre).
     */
    private function mejorCandidatoFlexible(\stdClass $r, int $escalon): array
    {
        if ($escalon === 1 || $escalon === 2) {
            $sql = "SELECT id g_id, inicio, fin, denominacion,
                           codigo_grupo_accion_formativa accion, codigo_grupo
                    FROM grupos
                    WHERE cif = ? AND inicio = ? AND fin = ?
                      AND denominacion LIKE CONCAT('%', ?, '%')
                    ORDER BY id ASC";
            $params = [$r->empresa_cif, $r->fi_legacy, $r->ff_legacy, $r->primer_nombre];
        } elseif ($escalon === 3) {
            $sql = "SELECT id g_id, inicio, fin, denominacion,
                           codigo_grupo_accion_formativa accion, codigo_grupo
                    FROM grupos
                    WHERE cif = ? AND inicio = ?
                      AND denominacion LIKE CONCAT('%', ?, '%')
                    ORDER BY id ASC";
            $params = [$r->empresa_cif, $r->fi_legacy, $r->primer_nombre];
        } elseif ($escalon === 4 || $escalon === 5) {
            $sql = "SELECT id g_id, inicio, fin, denominacion,
                           codigo_grupo_accion_formativa accion, codigo_grupo
                    FROM grupos
                    WHERE cif = ? AND inicio = ?
                    ORDER BY id ASC";
            $params = [$r->empresa_cif, $r->fi_legacy];
        } else {
            return [];
        }

        $rows = DB::select($sql, $params);
        if (empty($rows)) {
            return [];
        }

        $first = $rows[0];
        return [
            'g_id' => $first->g_id,
            'inicio' => $first->inicio,
            'fin' => $first->fin,
            'denominacion' => $first->denominacion,
            'accion' => $first->accion,
            'codigo_grupo' => $first->codigo_grupo,
            'n' => count($rows),
        ];
    }

    private function candidatoPorCifFecha(string $cif, string $fechaInicio): array
    {
        $rows = DB::select("
            SELECT id g_id, inicio, fin, denominacion,
                   codigo_grupo_accion_formativa accion, codigo_grupo
            FROM grupos
            WHERE cif = ? AND inicio = ?
            ORDER BY id ASC
        ", [$cif, $fechaInicio]);

        if (empty($rows)) {
            return [];
        }
        $first = $rows[0];
        return [
            'g_id' => $first->g_id,
            'inicio' => $first->inicio,
            'fin' => $first->fin,
            'denominacion' => $first->denominacion,
            'accion' => $first->accion,
            'codigo_grupo' => $first->codigo_grupo,
            'n' => count($rows),
        ];
    }

    private function razonSocialDeCif(?string $cif): string
    {
        if (!$cif) {
            return '';
        }
        $row = DB::selectOne('SELECT razon_social FROM empresas WHERE cif = ? LIMIT 1', [$cif]);
        return $row->razon_social ?? '';
    }

    /**
     * Exporta CSV con candidatos rescatables (escalones 2 y 3 — los más prometedores).
     * Para cada curso emite UN candidato (el primero por g.id ASC) y marca si había ambigüedad.
     */
    private function exportarCsv(string $path, array $clasificacion): void
    {
        $rescatables = array_filter($clasificacion, fn ($c) => in_array($c['escalon'], [2, 3], true));

        if (empty($rescatables)) {
            $this->warn("No hay casos en escalones 2 ni 3. No se genera CSV.");
            return;
        }

        $absPath = $this->resolverRutaCsv($path);
        $dir = dirname($absPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $fh = fopen($absPath, 'w');
        fputcsv($fh, [
            'escalon', 'curso_legacy_id', 'nif', 'alumno_nombre', 'alumno_apellido1',
            'empresa_cif', 'curso_titulo', 'fecha_inicio_legacy', 'fecha_fin_legacy',
            'candidato_grupo_id', 'candidato_inicio', 'candidato_fin',
            'candidato_denominacion', 'candidato_accion', 'candidato_codigo_grupo',
            'n_candidatos_escalon',
        ]);

        $emitidos = 0;
        foreach ($rescatables as $cursoId => $c) {
            $r = $c['row'];
            $candidato = $this->mejorCandidato($r, $c['escalon']);

            fputcsv($fh, [
                $c['escalon'],
                $cursoId,
                $r->nif,
                $r->alumno_nombre,
                $r->alumno_apellido1,
                $r->empresa_cif,
                $r->curso_titulo,
                $r->fi_legacy,
                $r->ff_legacy,
                $candidato['g_id'] ?? '',
                $candidato['inicio'] ?? '',
                $candidato['fin'] ?? '',
                $candidato['denominacion'] ?? '',
                $candidato['accion'] ?? '',
                $candidato['codigo_grupo'] ?? '',
                $candidato['n'] ?? 0,
            ]);
            $emitidos++;
        }
        fclose($fh);

        $this->info("CSV escrito: {$absPath} ({$emitidos} filas)");
    }

    private function mejorCandidato(\stdClass $r, int $escalon): array
    {
        if ($escalon === 2) {
            $sql = "SELECT id g_id, inicio, fin, denominacion,
                           codigo_grupo_accion_formativa accion, codigo_grupo
                    FROM grupos
                    WHERE cif = ? AND inicio = ? AND fin = ?
                      AND denominacion LIKE CONCAT('%', ?, '%')
                    ORDER BY id ASC";
            $params = [$r->empresa_cif, $r->fi_legacy, $r->ff_legacy, $r->primer_nombre];
        } else {
            // escalón 3: relaja fecha_fin
            $sql = "SELECT id g_id, inicio, fin, denominacion,
                           codigo_grupo_accion_formativa accion, codigo_grupo
                    FROM grupos
                    WHERE cif = ? AND inicio = ?
                      AND denominacion LIKE CONCAT('%', ?, '%')
                    ORDER BY id ASC";
            $params = [$r->empresa_cif, $r->fi_legacy, $r->primer_nombre];
        }

        $rows = DB::select($sql, $params);
        if (empty($rows)) {
            return [];
        }

        $first = $rows[0];
        return [
            'g_id' => $first->g_id,
            'inicio' => $first->inicio,
            'fin' => $first->fin,
            'denominacion' => $first->denominacion,
            'accion' => $first->accion,
            'codigo_grupo' => $first->codigo_grupo,
            'n' => count($rows),
        ];
    }

    private function resolverRutaCsv(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }
        return base_path($path);
    }
}
