<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\LegacyMappings;
use App\Models\Alumno;
use App\Models\AlumnoLegacyCurso;
use App\Models\AlumnoLegacyPool;
use App\Models\Empresa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class MigrarLegacy extends Command
{
    use LegacyMappings;

    protected $signature = 'alumnos:migrar-legacy
        {--dry-run : Preview sin escribir cambios}
        {--force : Actualizar alumnos existentes en alumnos}
        {--solo-pool : Solo poblar alumnos_legacy_pool, no crear alumnos ni cursos}
        {--solo-alumnos : Solo crear alumnos a partir del pool ya existente}
        {--solo-cursos : Solo poblar alumnos_legacy_cursos (historial de cursos legacy)}
        {--sin-fuzzy-razon-social : No usar fuzzy match company<->razon_social}
        {--limit=0 : Limitar el número de members procesados (debug)}';

    protected $description = 'Importa una vez datos de webcourses2014 a alumnos_legacy_pool y crea alumnos para los NIFs con empresa derivable';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $soloPool = (bool) $this->option('solo-pool');
        $soloAlumnos = (bool) $this->option('solo-alumnos');
        $soloCursos = (bool) $this->option('solo-cursos');
        $sinFuzzy = (bool) $this->option('sin-fuzzy-razon-social');
        $limit = (int) $this->option('limit');

        if ($dryRun) {
            $this->warn('--- DRY-RUN: no se escribirá nada ---');
        }

        if (!$soloAlumnos && !$soloCursos) {
            $this->configurarConexionLegacy();
            $this->faseAPool($dryRun, $limit);
        }

        if (!$soloPool && !$soloCursos) {
            $this->faseBAlumnos($dryRun, $force, $sinFuzzy);
        }

        if (!$soloPool && !$soloAlumnos) {
            if (!$soloCursos) {
                // ya configuramos en faseA si llegamos aquí desde el flujo normal
            } else {
                $this->configurarConexionLegacy();
            }
            $this->faseDCursos($dryRun, $limit);

            // Fase E: enriquecer acción/grupo de los cursos legacy desde grupos FUNDAE y participantes_bonificados
            if (!$dryRun) {
                $this->newLine();
                $this->info('=== Fase E: enriquecer acción/grupo de los cursos legacy ===');
                Artisan::call('alumnos:enriquecer-cursos-legacy', [], $this->getOutput());
            }
        }

        return self::SUCCESS;
    }

    private function configurarConexionLegacy(): void
    {
        $legacyDatabase = env('LEGACY_DB_DATABASE', 'webcourses2014');

        config([
            'database.connections.legacy' => array_merge(
                config('database.connections.mysql'),
                [
                    'database' => $legacyDatabase,
                    'strict' => false,
                ]
            ),
        ]);

        try {
            DB::connection('legacy')->getPdo();
        } catch (\Exception $e) {
            $this->error('Error al conectar con webcourses2014: ' . $e->getMessage());
            exit(1);
        }
    }

    /**
     * Fase A: Replicar tbl_member a alumnos_legacy_pool por NIF persona único.
     */
    private function faseAPool(bool $dryRun, int $limit): void
    {
        $this->info('=== Fase A: poblar alumnos_legacy_pool ===');

        $query = DB::connection('legacy')
            ->table('tbl_member')
            ->where('user_type', 'user')
            ->whereNotNull('personal_id')
            ->where('personal_id', '!=', '')
            ->where('personal_id', '!=', '0')
            ->whereRaw("UPPER(REPLACE(REPLACE(TRIM(personal_id),' ',''),'-','')) REGEXP '^[0-9]{8}[A-Z]\$|^[XYZ][0-9]{7}[A-Z]\$'")
            ->orderByDesc('mem_id');

        $total = $query->count();
        $this->info("Members con NIF persona válido en legacy: {$total}");

        if ($limit > 0) {
            $query = $query->limit($limit);
            $this->warn("LIMIT aplicado: {$limit}");
        }

        $procesados = 0;
        $nuevos = 0;
        $actualizados = 0;
        $vistos = [];

        $bar = $this->output->createProgressBar($limit > 0 ? min($limit, $total) : $total);
        $bar->start();

        foreach ($query->lazy(500) as $row) {
            $nif = $this->normalizarNif($row->personal_id);
            if (!$nif || isset($vistos[$nif])) {
                $bar->advance();
                continue;
            }
            $vistos[$nif] = true;

            $apellidos = $this->separarApellidos($row->last_name);
            $telefono = $row->phone ?: ($row->mobile ?? null);
            $fechaNac = $this->parsearFechaLegacy($row->dob ?? null);
            $niss = $row->niis ?: ($row->social_security_number ?? null);
            $cifResuelto = $this->resolverCifLegacy($row->nid ?? null, $row->company ?? null);

            $datos = [
                'nif' => $nif,
                'nombre' => $row->first_name ?: null,
                'apellido1' => $apellidos['apellido1'] ?: null,
                'apellido2' => $apellidos['apellido2'] ?: null,
                'email' => $row->email ?: null,
                'telefono' => $telefono ?: null,
                'niss' => $niss ?: null,
                'fecha_nacimiento' => $fechaNac,
                'nivel_estudios' => $this->mapearNivelEstudios($row->level_of_studies ?? null),
                'categoria_profesional' => $this->mapearCategoriaProfesional($row->professional_category ?? null),
                'grupo_cotizacion_tgss' => $this->mapearGrupoCotizacion($row->listed_group ?? null),
                'legacy_nid' => $row->nid ?: null,
                'legacy_company_text' => $row->company ?: null,
                'legacy_cif_resuelto' => $cifResuelto,
                'source_mem_id' => (int) $row->mem_id,
                'imported_at' => now(),
            ];

            if (!$dryRun) {
                $existente = AlumnoLegacyPool::where('nif', $nif)->first();
                if ($existente) {
                    $existente->update($datos);
                    $actualizados++;
                } else {
                    AlumnoLegacyPool::create($datos);
                    $nuevos++;
                }
            } else {
                $nuevos++;
            }

            $procesados++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Pool: {$procesados} NIFs únicos procesados ({$nuevos} nuevos, {$actualizados} actualizados)");
    }

    /**
     * Fase B: Crear alumnos a partir del pool, resolviendo empresa por:
     *   a) legacy_cif_resuelto (nid o company-CIF) ↔ empresas.cif
     *   b) Fuzzy razón social legacy_company_text ↔ empresas.razon_social
     */
    private function faseBAlumnos(bool $dryRun, bool $force, bool $sinFuzzy): void
    {
        $this->info('=== Fase B: crear alumnos a partir del pool ===');

        $totalPool = AlumnoLegacyPool::count();
        $this->info("Entradas en pool: {$totalPool}");

        if ($totalPool === 0) {
            $this->warn('Pool vacío. Ejecuta primero sin --solo-alumnos.');
            return;
        }

        // Cache empresas por CIF y razón social normalizada
        $empresasPorCif = [];
        $empresasPorRazon = [];
        foreach (Empresa::query()->get(['id', 'cif', 'razon_social']) as $e) {
            $cif = $this->normalizarCif($e->cif);
            if ($cif) {
                $empresasPorCif[$cif] = $e->id;
            }
            $razon = $this->normalizarRazonSocial($e->razon_social);
            if ($razon) {
                if (!isset($empresasPorRazon[$razon])) {
                    $empresasPorRazon[$razon] = [];
                }
                $empresasPorRazon[$razon][] = $e->id;
            }
        }

        $creadosPorNid = 0;
        $creadosPorCompanyCif = 0;
        $creadosPorRazonSocial = 0;
        $actualizados = 0;
        $yaExisten = 0;
        $sinEmpresa = 0;
        $conflictos = [];

        $bar = $this->output->createProgressBar($totalPool);
        $bar->start();

        AlumnoLegacyPool::query()->lazy(500)->each(function ($pool) use (
            &$creadosPorNid, &$creadosPorCompanyCif, &$creadosPorRazonSocial,
            &$actualizados, &$yaExisten, &$sinEmpresa, &$conflictos,
            $empresasPorCif, $empresasPorRazon, $dryRun, $force, $sinFuzzy, $bar
        ) {
            $bar->advance();

            $empresaId = null;
            $estrategia = null;

            // a) cif_resuelto (proviene de nid o company)
            if ($pool->legacy_cif_resuelto && isset($empresasPorCif[$pool->legacy_cif_resuelto])) {
                $empresaId = $empresasPorCif[$pool->legacy_cif_resuelto];
                $cifEmpresa = $pool->legacy_cif_resuelto;
                $estrategia = $this->normalizarCif($pool->legacy_nid) === $cifEmpresa ? 'nid' : 'company_cif';
            }

            // b) fuzzy razon social
            if (!$empresaId && !$sinFuzzy && $pool->legacy_company_text) {
                $razon = $this->normalizarRazonSocial($pool->legacy_company_text);
                if ($razon && isset($empresasPorRazon[$razon])) {
                    $candidatos = $empresasPorRazon[$razon];
                    if (count($candidatos) === 1) {
                        $empresaId = $candidatos[0];
                        $estrategia = 'razon_social';
                    } else {
                        $conflictos[] = "{$pool->nif} ({$pool->nombre} {$pool->apellido1}) — razón social ambigua: '{$pool->legacy_company_text}' matchea " . count($candidatos) . ' empresas';
                    }
                }
            }

            if (!$empresaId) {
                $sinEmpresa++;
                return;
            }

            $existente = Alumno::where('nif', $pool->nif)->where('empresa_id', $empresaId)->first();

            // Detectar alumno existente con mismo nombre+apellido1+empresa pero NIF distinto
            // (caso real: NIF persona divergente entre legacy y FUNDAE — Juan Sanchez)
            if (!$existente && $pool->nombre && $pool->apellido1) {
                $homonimos = Alumno::where('empresa_id', $empresaId)
                    ->whereRaw('UPPER(TRIM(nombre)) = ?', [strtoupper(trim($pool->nombre))])
                    ->whereRaw('UPPER(TRIM(apellido1)) = ?', [strtoupper(trim($pool->apellido1))])
                    ->where('nif', '!=', $pool->nif)
                    ->limit(2)
                    ->get();

                if ($homonimos->count() === 1) {
                    $alumnoCanonico = $homonimos->first();
                    if (!$alumnoCanonico->email && $pool->email) {
                        if (!$dryRun) {
                            $alumnoCanonico->fill([
                                'email' => $pool->email,
                                'telefono' => $alumnoCanonico->telefono ?: $pool->telefono,
                                'fecha_nacimiento' => $alumnoCanonico->fecha_nacimiento ?: $pool->fecha_nacimiento,
                                'nivel_estudios' => $alumnoCanonico->nivel_estudios ?: $pool->nivel_estudios,
                                'categoria_profesional' => $alumnoCanonico->categoria_profesional ?: $pool->categoria_profesional,
                                'grupo_cotizacion_tgss' => $alumnoCanonico->grupo_cotizacion_tgss ?: $pool->grupo_cotizacion_tgss,
                                'datos_pendientes' => false,
                            ])->save();
                        }
                        $actualizados++;
                    } else {
                        $yaExisten++;
                    }
                    return;
                }

                if ($homonimos->count() > 1) {
                    $conflictos[] = "{$pool->nif} ({$pool->nombre} {$pool->apellido1}) en empresa {$empresaId}: hay {$homonimos->count()} homónimos con NIFs distintos";
                    return;
                }
            }

            if ($existente && !$force) {
                $yaExisten++;
                return;
            }

            $datos = [
                'empresa_id' => $empresaId,
                'nombre' => $pool->nombre,
                'apellido1' => $pool->apellido1,
                'apellido2' => $pool->apellido2,
                'nif' => $pool->nif,
                'email' => $pool->email,
                'telefono' => $pool->telefono,
                'niss' => $pool->niss,
                'fecha_nacimiento' => $pool->fecha_nacimiento,
                'nivel_estudios' => $pool->nivel_estudios,
                'categoria_profesional' => $pool->categoria_profesional,
                'grupo_cotizacion_tgss' => $pool->grupo_cotizacion_tgss,
                'datos_pendientes' => false,
            ];

            if (!$dryRun) {
                if ($existente) {
                    $existente->update($datos);
                    $actualizados++;
                } else {
                    try {
                        Alumno::create($datos);
                        $this->incrementarEstrategia($estrategia, $creadosPorNid, $creadosPorCompanyCif, $creadosPorRazonSocial);
                    } catch (\Exception $e) {
                        $conflictos[] = "{$pool->nif} (empresa_id={$empresaId}): " . $e->getMessage();
                    }
                }
            } else {
                if ($existente) {
                    $actualizados++;
                } else {
                    $this->incrementarEstrategia($estrategia, $creadosPorNid, $creadosPorCompanyCif, $creadosPorRazonSocial);
                }
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info('=== Resumen Fase B ===');
        $this->line("Alumnos creados via nid → empresas.cif:               {$creadosPorNid}");
        $this->line("Alumnos creados via company(CIF) → empresas.cif:      {$creadosPorCompanyCif}");
        $this->line("Alumnos creados via fuzzy razón social:               {$creadosPorRazonSocial}");
        $this->line("Alumnos actualizados (--force):                       {$actualizados}");
        $this->line("Ya existían (omitidos):                               {$yaExisten}");
        $this->line("Pool sin empresa derivable en Panel:                  {$sinEmpresa}");

        if (!empty($conflictos)) {
            $this->newLine();
            $this->warn('Conflictos detectados (' . count($conflictos) . '):');
            foreach (array_slice($conflictos, 0, 30) as $c) {
                $this->warn("  - {$c}");
            }
            if (count($conflictos) > 30) {
                $this->warn('  ... ' . (count($conflictos) - 30) . ' más');
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('--- Ejecuta sin --dry-run para aplicar los cambios ---');
        }
    }

    /**
     * Fase D: Replica tbl_member_courses (con info de tbl_courses) en alumnos_legacy_cursos.
     * Vincula por NIF persona del legacy → alumnos.nif a través del JOIN con tbl_member.
     */
    private function faseDCursos(bool $dryRun, int $limit): void
    {
        $this->info('=== Fase D: poblar alumnos_legacy_cursos (historial cursos legacy) ===');

        $query = DB::connection('legacy')
            ->table('tbl_member_courses AS mc')
            ->join('tbl_member AS m', 'm.mem_id', '=', 'mc.mem_id')
            ->join('tbl_courses AS c', 'c.cid', '=', 'mc.course_id')
            ->whereRaw("UPPER(REPLACE(REPLACE(TRIM(m.personal_id),' ',''),'-','')) REGEXP '^[0-9]{8}[A-Z]\$|^[XYZ][0-9]{7}[A-Z]\$'")
            ->select(
                'mc.mc_id', 'mc.mem_id', 'mc.course_id', 'mc.c_start_date', 'mc.c_end_date',
                'mc.c_status', 'mc.c_result', 'mc.formation_group_alpha as mc_alpha', 'mc.formation_group_number as mc_number',
                'm.personal_id', 'm.company', 'm.nid',
                'c.c_title', 'c.course_short_name', 'c.course_hours'
            )
            ->orderBy('mc.mc_id');

        $total = $query->count();
        $this->info("Cursos legacy con NIF persona válido: {$total}");

        if ($limit > 0) {
            $query = $query->limit($limit);
            $this->warn("LIMIT aplicado: {$limit}");
        }

        $procesados = 0;
        $nuevos = 0;
        $actualizados = 0;

        $bar = $this->output->createProgressBar($limit > 0 ? min($limit, $total) : $total);
        $bar->start();

        foreach ($query->lazy(500) as $row) {
            $nif = $this->normalizarNif($row->personal_id);
            if (!$nif) {
                $bar->advance();
                continue;
            }

            $cifResuelto = $this->resolverCifLegacy($row->nid ?? null, $row->company ?? null);

            $datos = [
                'nif' => $nif,
                'course_id' => (int) $row->course_id,
                'curso_titulo' => $row->c_title ?: null,
                'curso_short_name' => $row->course_short_name ?: null,
                'curso_horas' => (int) ($row->course_hours ?? 0) ?: null,
                'fecha_inicio' => $this->parsearFechaLegacy($row->c_start_date ?? null),
                'fecha_fin' => $this->parsearFechaLegacy($row->c_end_date ?? null),
                'estado_curso' => $row->c_status ?: null,
                'resultado' => $row->c_result ?: null,
                'formation_group_alpha' => $row->mc_alpha ?: null,
                'formation_group_number' => $row->mc_number ?: null,
                'legacy_company_text' => $row->company ?: null,
                'legacy_cif_resuelto' => $cifResuelto,
                'source_mc_id' => (int) $row->mc_id,
                'source_mem_id' => (int) $row->mem_id,
                'imported_at' => now(),
            ];

            if (!$dryRun) {
                $existente = AlumnoLegacyCurso::where('source_mc_id', (int) $row->mc_id)->first();
                if ($existente) {
                    $existente->update($datos);
                    $actualizados++;
                } else {
                    AlumnoLegacyCurso::create($datos);
                    $nuevos++;
                }
            } else {
                $nuevos++;
            }

            $procesados++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Cursos legacy: {$procesados} procesados ({$nuevos} nuevos, {$actualizados} actualizados)");
    }

    private function incrementarEstrategia(?string $estrategia, int &$nid, int &$companyCif, int &$razon): void
    {
        match ($estrategia) {
            'nid' => $nid++,
            'company_cif' => $companyCif++,
            'razon_social' => $razon++,
            default => null,
        };
    }

    /**
     * Parsea una fecha legacy aceptando 'YYYY-MM-DD', 'dd/MM/yyyy', 'd-M-yyyy' y variantes.
     * Devuelve string YYYY-MM-DD o null si no se puede.
     */
    private function parsearFechaLegacy(?string $valor): ?string
    {
        if (!$valor) {
            return null;
        }

        $trim = trim($valor);
        if ($trim === '' || $trim === '0000-00-00') {
            return null;
        }

        $formatos = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'd/m/y', 'd-m-y', 'Y/m/d'];
        foreach ($formatos as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $trim);
            if ($dt !== false) {
                $año = (int) $dt->format('Y');
                if ($año < 1920 || $año > (int) date('Y')) {
                    continue;
                }
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }
}
