<?php

namespace App\Console\Commands;

use App\Models\AlumnoLegacyCurso;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnriquecerCursosLegacy extends Command
{
    protected $signature = 'alumnos:enriquecer-cursos-legacy
        {--dry-run : Preview sin escribir cambios}
        {--force : Sobrescribir formation_group_alpha existente}';

    protected $description = 'Cruza alumnos_legacy_cursos sin acción/grupo con la tabla `grupos` (FUNDAE importada) y `participantes_bonificados` para rellenar acción, grupo y grupo_id_fundae';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($dryRun) {
            $this->warn('--- DRY-RUN: no se escribirá nada ---');
        }

        $totalSinDatos = AlumnoLegacyCurso::query()
            ->where(function ($q) {
                $q->whereNull('formation_group_alpha')->orWhere('formation_group_alpha', '');
            })->count();

        $this->info("Cursos legacy sin acción/grupo: {$totalSinDatos}");

        $enriquecidosViaGrupos = $this->enriquecerViaGrupos($dryRun, $force);
        $enriquecidosViaParticipantes = $this->enriquecerViaParticipantes($dryRun, $force);

        $this->newLine();
        $this->info('=== RESUMEN ===');
        $this->line("Enriquecidos via tabla `grupos` (FUNDAE):           {$enriquecidosViaGrupos}");
        $this->line("Enriquecidos via `participantes_bonificados`:       {$enriquecidosViaParticipantes}");
        $this->line('Total:                                              ' . ($enriquecidosViaGrupos + $enriquecidosViaParticipantes));

        if ($dryRun) {
            $this->newLine();
            $this->warn('--- Ejecuta sin --dry-run para aplicar los cambios ---');
        }

        return self::SUCCESS;
    }

    /**
     * Enriquece cruzando con tabla `grupos` (importada de FUNDAE) por:
     *   alumno.empresa.cif = grupos.cif
     *   alumno_legacy_curso.fecha_inicio = grupos.inicio
     *   alumno_legacy_curso.fecha_fin = grupos.fin
     *   grupos.denominacion contiene el nombre del alumno
     *
     * Toma codigo_grupo_accion_formativa (acción), codigo_grupo (grupo) y grupo_id (grupo_id_fundae).
     */
    private function enriquecerViaGrupos(bool $dryRun, bool $force): int
    {
        $this->info('--- Cruce con tabla `grupos` ---');

        $sqlForceFilter = $force ? '' : "AND (c.formation_group_alpha IS NULL OR c.formation_group_alpha = '')";

        $rows = DB::select("
            SELECT
                c.id AS curso_legacy_id,
                g.codigo_grupo_accion_formativa AS accion,
                g.codigo_grupo AS grupo,
                g.grupo_id AS grupo_id_fundae
            FROM alumnos_legacy_cursos c
            INNER JOIN alumnos a ON a.nif = c.nif
            INNER JOIN empresas e ON e.id = a.empresa_id
            INNER JOIN grupos g
              ON g.cif = e.cif
              AND g.inicio = c.fecha_inicio
              AND g.fin = c.fecha_fin
              AND g.denominacion LIKE CONCAT('%', a.nombre, '%')
            WHERE 1=1 {$sqlForceFilter}
        ");

        $count = count($rows);
        $this->info("  Matches encontrados: {$count}");

        if (!$dryRun && $count > 0) {
            $bar = $this->output->createProgressBar($count);
            $bar->start();
            foreach ($rows as $r) {
                AlumnoLegacyCurso::where('id', $r->curso_legacy_id)->update([
                    'formation_group_alpha' => $r->accion,
                    'formation_group_number' => $r->grupo,
                    'grupo_id_fundae' => $r->grupo_id_fundae,
                    'origen_enriquecimiento' => 'grupos_fundae',
                ]);
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
        }

        return $count;
    }

    /**
     * Enriquece cruzando con `participantes_bonificados` por:
     *   alumno_legacy_curso.nif = participantes_bonificados.nif_participante
     *   fechas iguales
     *
     * El id_codigo_grupo tiene formato "(grupo_id) accion/grupo".
     */
    private function enriquecerViaParticipantes(bool $dryRun, bool $force): int
    {
        $this->info('--- Cruce con `participantes_bonificados` ---');

        $sqlForceFilter = $force ? '' : "AND (c.formation_group_alpha IS NULL OR c.formation_group_alpha = '')";

        $rows = DB::select("
            SELECT
                c.id AS curso_legacy_id,
                pb.id_codigo_grupo
            FROM alumnos_legacy_cursos c
            INNER JOIN participantes_bonificados pb
              ON pb.nif_participante = c.nif
              AND pb.fecha_inicio = c.fecha_inicio
              AND pb.fecha_fin = c.fecha_fin
            WHERE pb.id_codigo_grupo IS NOT NULL
              AND pb.id_codigo_grupo != ''
              {$sqlForceFilter}
        ");

        $procesados = 0;

        if (!$dryRun && count($rows) > 0) {
            $bar = $this->output->createProgressBar(count($rows));
            $bar->start();
            foreach ($rows as $r) {
                $datos = $this->parsearIdCodigoGrupo($r->id_codigo_grupo);
                if (!$datos) {
                    $bar->advance();
                    continue;
                }
                AlumnoLegacyCurso::where('id', $r->curso_legacy_id)->update([
                    'formation_group_alpha' => $datos['accion'],
                    'formation_group_number' => $datos['grupo'],
                    'grupo_id_fundae' => $datos['grupo_id_fundae'],
                    'origen_enriquecimiento' => 'participantes_bonificados',
                ]);
                $procesados++;
                $bar->advance();
            }
            $bar->finish();
            $this->newLine();
        } else {
            foreach ($rows as $r) {
                if ($this->parsearIdCodigoGrupo($r->id_codigo_grupo)) {
                    $procesados++;
                }
            }
        }

        $this->info("  Matches procesados: {$procesados}");
        return $procesados;
    }

    /**
     * Parsea "(75143) 201/1" → ['grupo_id_fundae' => '75143', 'accion' => '201', 'grupo' => '1']
     */
    private function parsearIdCodigoGrupo(string $codigo): ?array
    {
        if (!preg_match('/\((\d+)\)\s*(\d+)\/(\d+)/', $codigo, $m)) {
            return null;
        }

        return [
            'grupo_id_fundae' => $m[1],
            'accion' => $m[2],
            'grupo' => $m[3],
        ];
    }
}
