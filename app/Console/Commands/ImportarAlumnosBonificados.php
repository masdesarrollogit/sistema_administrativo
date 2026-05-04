<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\LegacyMappings;
use App\Models\Alumno;
use App\Models\AlumnoLegacyPool;
use App\Models\Empresa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarAlumnosBonificados extends Command
{
    use LegacyMappings;

    protected $signature = 'alumnos:importar-bonificados
        {--dry-run : Preview sin insertar datos}
        {--force : Actualizar alumnos que ya existen}';

    protected $description = 'Crea/actualiza alumnos a partir de participantes_bonificados, enriqueciendo con alumnos_legacy_pool por NIF y por nombre+empresa';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if ($dryRun) {
            $this->warn('--- DRY-RUN: no se insertarán datos ---');
        }

        $participantes = DB::table('participantes_bonificados')
            ->select('nif_participante', 'nombre', 'cif', 'niss')
            ->distinct()
            ->get()
            ->unique('nif_participante');

        $this->info("Participantes bonificados únicos: {$participantes->count()}");

        $creadosPorPoolNif = 0;
        $creadosPorPoolNombre = 0;
        $creadosMinimos = 0;
        $actualizados = 0;
        $yaExisten = 0;
        $sinEmpresa = [];
        $errores = 0;

        foreach ($participantes as $pb) {
            $nif = $this->normalizarNif($pb->nif_participante);
            if (!$nif) {
                continue;
            }

            $empresa = Empresa::where('cif', $pb->cif)->first();
            if (!$empresa) {
                $sinEmpresa[] = "{$nif} ({$pb->nombre}) - CIF: {$pb->cif}";
                $errores++;
                continue;
            }

            $alumnoExistente = Alumno::where('nif', $nif)->where('empresa_id', $empresa->id)->first();
            if ($alumnoExistente && !$force) {
                $yaExisten++;
                continue;
            }

            // Buscar en pool local por NIF
            $pool = AlumnoLegacyPool::where('nif', $nif)->first();

            // Fallback: buscar por nombre+empresa cuando no hay match por NIF
            // Cubre casos donde NIF persona difiere entre legacy y FUNDAE (ej. Juan Sanchez)
            if (!$pool) {
                $pool = $this->buscarPorNombreEmpresa($pb->nombre, $empresa);
            }

            if ($pool) {
                $datos = [
                    'empresa_id' => $empresa->id,
                    'nombre' => $pool->nombre ?: $this->separarNombreCompleto($pb->nombre)['nombre'],
                    'apellido1' => $pool->apellido1 ?: $this->separarNombreCompleto($pb->nombre)['apellido1'],
                    'apellido2' => $pool->apellido2 ?: $this->separarNombreCompleto($pb->nombre)['apellido2'],
                    'nif' => $nif,
                    'email' => $pool->email,
                    'telefono' => $pool->telefono,
                    'niss' => $pb->niss ?: $pool->niss,
                    'fecha_nacimiento' => $pool->fecha_nacimiento,
                    'nivel_estudios' => $pool->nivel_estudios,
                    'categoria_profesional' => $pool->categoria_profesional,
                    'grupo_cotizacion_tgss' => $pool->grupo_cotizacion_tgss,
                    'datos_pendientes' => empty($pool->email),
                ];

                if (!$dryRun) {
                    try {
                        if ($alumnoExistente && $force) {
                            $alumnoExistente->update($datos);
                            $actualizados++;
                        } else {
                            Alumno::create($datos);
                            if ($pool->nif === $nif) {
                                $creadosPorPoolNif++;
                            } else {
                                $creadosPorPoolNombre++;
                            }
                        }
                    } catch (\Exception $e) {
                        $this->error("Error procesando {$nif}: " . $e->getMessage());
                        $errores++;
                    }
                } else {
                    if ($alumnoExistente) {
                        $actualizados++;
                    } else {
                        $pool->nif === $nif ? $creadosPorPoolNif++ : $creadosPorPoolNombre++;
                    }
                }

                continue;
            }

            // Sin match: crear alumno mínimo + flag datos_pendientes
            $partes = $this->separarNombreCompleto($pb->nombre);
            $datos = [
                'empresa_id' => $empresa->id,
                'nombre' => $partes['nombre'],
                'apellido1' => $partes['apellido1'],
                'apellido2' => $partes['apellido2'],
                'nif' => $nif,
                'niss' => $pb->niss ?: null,
                'datos_pendientes' => true,
            ];

            if (!$dryRun) {
                try {
                    if ($alumnoExistente && $force) {
                        $alumnoExistente->update($datos);
                        $actualizados++;
                    } else {
                        Alumno::create($datos);
                        $creadosMinimos++;
                    }
                } catch (\Exception $e) {
                    $this->error("Error creando {$nif}: " . $e->getMessage());
                    $errores++;
                }
            } else {
                $alumnoExistente ? $actualizados++ : $creadosMinimos++;
            }
        }

        $this->newLine();
        $this->info('=== RESUMEN ===');
        $this->line("Creados con email del pool (match NIF):    {$creadosPorPoolNif}");
        $this->line("Creados con email del pool (match nombre): {$creadosPorPoolNombre}");
        $this->line("Creados mínimos (datos_pendientes=true):   {$creadosMinimos}");
        $this->line("Actualizados (--force):                    {$actualizados}");
        $this->line("Ya existían (omitidos):                    {$yaExisten}");

        if (!empty($sinEmpresa)) {
            $this->newLine();
            $this->error('Sin empresa en Panel (no creados):');
            foreach (array_slice($sinEmpresa, 0, 20) as $item) {
                $this->error("  - {$item}");
            }
            if (count($sinEmpresa) > 20) {
                $this->error('  ... ' . (count($sinEmpresa) - 20) . ' más');
            }
        }
        if ($errores > 0) {
            $this->error("Errores: {$errores}");
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('--- Ejecuta sin --dry-run para aplicar los cambios ---');
        }

        return self::SUCCESS;
    }

    /**
     * Busca en el pool una entrada cuya razón social coincida con la empresa
     * y cuyo nombre+apellidos coincidan con el del participante FUNDAE.
     * Devuelve la entrada solo si el match es único.
     */
    private function buscarPorNombreEmpresa(string $nombreCompleto, Empresa $empresa): ?AlumnoLegacyPool
    {
        $razonNorm = $this->normalizarRazonSocial($empresa->razon_social);
        $cifNorm = $this->normalizarCif($empresa->cif);

        if (!$razonNorm && !$cifNorm) {
            return null;
        }

        $partes = $this->separarNombreCompleto($nombreCompleto);
        $nombreBuscado = strtoupper(trim($partes['nombre']));
        $apellido1Buscado = strtoupper(trim($partes['apellido1']));

        if (!$nombreBuscado || !$apellido1Buscado) {
            return null;
        }

        $query = AlumnoLegacyPool::query();

        $query->where(function ($q) use ($razonNorm, $cifNorm) {
            if ($cifNorm) {
                $q->where('legacy_cif_resuelto', $cifNorm);
            }
            if ($razonNorm) {
                // Comparar normalizado: el legacy_company_text puede tener variantes
                $q->orWhereRaw(
                    "UPPER(REPLACE(REPLACE(REPLACE(legacy_company_text,'.',''),',',''),'  ',' ')) LIKE ?",
                    ['%' . $razonNorm . '%']
                );
            }
        });

        $query->whereRaw('UPPER(nombre) = ?', [$nombreBuscado])
              ->whereRaw('UPPER(apellido1) = ?', [$apellido1Buscado]);

        $candidatos = $query->limit(2)->get();

        return $candidatos->count() === 1 ? $candidatos->first() : null;
    }
}
