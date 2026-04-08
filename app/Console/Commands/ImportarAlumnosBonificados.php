<?php

namespace App\Console\Commands;

use App\Models\Alumno;
use App\Models\Empresa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarAlumnosBonificados extends Command
{
    protected $signature = 'alumnos:importar-bonificados
        {--dry-run : Preview sin insertar datos}
        {--force : Actualizar alumnos que ya existen}';

    protected $description = 'Importar alumnos desde participantes_bonificados + webcourses2014';

    /**
     * Mapeo: level_of_studies (texto legacy) → nivel_estudios (codigo FUNDAE 1-10)
     */
    private const NIVEL_ESTUDIOS = [
        'Menos que primaria' => 1,
        'Educación Primaria' => 2,
        'Primera Etapa de Educación secundaria' => 3,
        'Segunda Etapa de Educación Secundaria' => 4,
        'Educación post secundaria no superior' => 5,
        'Técnico Superior/FP grado superior y equivalentes' => 6,
        'E universitarios 1 er ciclo (Diplomatura-Grados)' => 7,
        'E universitarios 2 do ciclo (Licenciatura- Master)' => 8,
        'E universitarios 3 er ciclo (Doctorado)' => 9,
        'Otras Titulaciones' => 10,
    ];

    /**
     * Mapeo: professional_category (texto legacy) → categoria_profesional (codigo FUNDAE 1-5)
     */
    private const CATEGORIA_PROFESIONAL = [
        'Directivo' => 1,
        'Mando Intermedio' => 2,
        'Técnico' => 3,
        'Trabajador cualificado' => 4,
        'Trabajador con Baja cualificación' => 5,
    ];

    /**
     * Mapeo: listed_group (texto legacy) → grupo_cotizacion_tgss (codigo 1-11)
     */
    private const GRUPO_COTIZACION = [
        'Ingenieros y Licenciados' => '1',
        'Ingenieros Técnicos, peritos y ayudantes titulados' => '2',
        'Jefes administrativos y de taller' => '3',
        'Ayudantes no titulados' => '4',
        'Oficiales administrativos' => '5',
        'Subalternos' => '6',
        'Auxiliares administrativos' => '7',
        'Oficiales de primera y de segunda' => '8',
        'Oficiales de Tercera y especialistas' => '9',
        'Trabajadores mayores de 18 años no cualificados' => '10',
        'Trabajadores menos de 18 años' => '11',
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($dryRun) {
            $this->warn('--- MODO DRY-RUN: no se insertaran datos ---');
        }

        // Configurar conexion legacy: usa las credenciales principales apuntando a la DB webcourses2014
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
            return 1;
        }

        // Obtener NIFs unicos de participantes bonificados
        $participantes = DB::table('participantes_bonificados')
            ->select('nif_participante', 'nombre', 'cif', 'niss')
            ->distinct()
            ->get()
            ->unique('nif_participante');

        $this->info("Participantes bonificados unicos: {$participantes->count()}");

        $creados = 0;
        $actualizados = 0;
        $sinMatch = [];
        $sinEmpresa = [];
        $yaExisten = 0;
        $errores = 0;

        $tableData = [];

        foreach ($participantes as $pb) {
            $nif = strtoupper(trim($pb->nif_participante));
            if (!$nif) {
                continue;
            }

            // Buscar empresa en el Panel por CIF
            $empresa = Empresa::where('cif', $pb->cif)->first();
            if (!$empresa) {
                $sinEmpresa[] = "{$nif} ({$pb->nombre}) - CIF: {$pb->cif}";
                $errores++;
                continue;
            }

            // Verificar si ya existe como alumno
            $alumnoExistente = Alumno::where('nif', $nif)->where('empresa_id', $empresa->id)->first();
            if ($alumnoExistente && !$force) {
                $yaExisten++;
                continue;
            }

            // Buscar datos complementarios en legacy (el mas reciente por mem_id)
            $legacy = DB::connection('legacy')
                ->table('tbl_member')
                ->where('user_type', 'user')
                ->whereRaw('UPPER(TRIM(personal_id)) = ?', [$nif])
                ->orderByDesc('mem_id')
                ->first();

            if (!$legacy) {
                $sinMatch[] = "{$nif} ({$pb->nombre}) - CIF: {$pb->cif}";

                // Crear con datos minimos de participantes_bonificados
                $nombreParts = $this->separarNombreCompleto($pb->nombre);
                $datos = [
                    'empresa_id' => $empresa->id,
                    'nombre' => $nombreParts['nombre'],
                    'apellido1' => $nombreParts['apellido1'],
                    'apellido2' => $nombreParts['apellido2'],
                    'nif' => $nif,
                    'niss' => $pb->niss ?: null,
                ];

                $tableData[] = [
                    $nif,
                    $nombreParts['nombre'],
                    $nombreParts['apellido1'] . ' ' . $nombreParts['apellido2'],
                    '(sin email)',
                    $pb->cif,
                    'SIN MATCH LEGACY',
                ];

                if (!$dryRun) {
                    try {
                        if ($alumnoExistente && $force) {
                            $alumnoExistente->update($datos);
                            $actualizados++;
                        } else {
                            Alumno::create($datos);
                            $creados++;
                        }
                    } catch (\Exception $e) {
                        $this->error("Error creando {$nif}: " . $e->getMessage());
                        $errores++;
                    }
                } else {
                    $creados++;
                }

                continue;
            }

            // Combinar datos de ambas fuentes
            $apellidos = $this->separarApellidos($legacy->last_name);
            $nivelEstudios = $this->mapearNivelEstudios($legacy->level_of_studies);
            $categoriaProfesional = $this->mapearCategoriaProfesional($legacy->professional_category);
            $grupoCotizacion = $this->mapearGrupoCotizacion($legacy->listed_group);
            $telefono = $legacy->phone ?: $legacy->mobile ?: null;
            $niss = $pb->niss ?: ($legacy->niis ?: $legacy->social_security_number ?: null);
            $fechaNacimiento = ($legacy->dob && $legacy->dob !== '0000-00-00') ? $legacy->dob : null;

            $datos = [
                'empresa_id' => $empresa->id,
                'nombre' => $legacy->first_name,
                'apellido1' => $apellidos['apellido1'],
                'apellido2' => $apellidos['apellido2'],
                'nif' => $nif,
                'email' => $legacy->email ?: null,
                'telefono' => $telefono,
                'niss' => $niss,
                'fecha_nacimiento' => $fechaNacimiento,
                'nivel_estudios' => $nivelEstudios,
                'categoria_profesional' => $categoriaProfesional,
                'grupo_cotizacion_tgss' => $grupoCotizacion,
            ];

            $tableData[] = [
                $nif,
                $legacy->first_name,
                $apellidos['apellido1'] . ' ' . $apellidos['apellido2'],
                $legacy->email ?: '(sin email)',
                $pb->cif,
                $alumnoExistente ? 'ACTUALIZAR' : 'CREAR',
            ];

            if (!$dryRun) {
                try {
                    if ($alumnoExistente && $force) {
                        $alumnoExistente->update($datos);
                        $actualizados++;
                    } else {
                        Alumno::create($datos);
                        $creados++;
                    }
                } catch (\Exception $e) {
                    $this->error("Error procesando {$nif}: " . $e->getMessage());
                    $errores++;
                }
            } else {
                if ($alumnoExistente) {
                    $actualizados++;
                } else {
                    $creados++;
                }
            }
        }

        // Mostrar tabla de resultados
        if (!empty($tableData)) {
            $this->newLine();
            $this->table(
                ['NIF', 'Nombre', 'Apellidos', 'Email', 'CIF', 'Accion'],
                $tableData
            );
        }

        // Resumen
        $this->newLine();
        $this->info("=== RESUMEN ===");
        $this->info("Creados: {$creados}");
        if ($actualizados > 0) {
            $this->info("Actualizados: {$actualizados}");
        }
        if ($yaExisten > 0) {
            $this->warn("Ya existian (omitidos): {$yaExisten}");
        }
        if (!empty($sinMatch)) {
            $this->warn("Sin match en legacy (creados con datos minimos):");
            foreach ($sinMatch as $item) {
                $this->warn("  - {$item}");
            }
        }
        if (!empty($sinEmpresa)) {
            $this->error("Sin empresa en Panel (no creados):");
            foreach ($sinEmpresa as $item) {
                $this->error("  - {$item}");
            }
        }
        if ($errores > 0) {
            $this->error("Errores: {$errores}");
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('--- Ejecuta sin --dry-run para aplicar los cambios ---');
        }

        return 0;
    }

    /**
     * Separar "Escalona Alarcon" en apellido1 y apellido2.
     * Asume que el primer token es apellido1 y el resto apellido2.
     */
    private function separarApellidos(string $lastNameCompleto): array
    {
        // Limpiar sufijos del legacy como "(REPASO)", "(repaso)", etc.
        $limpio = preg_replace('/\s*\(.*?\)\s*$/', '', trim($lastNameCompleto));
        $parts = preg_split('/\s+/', trim($limpio), 2);

        return [
            'apellido1' => $parts[0] ?? '',
            'apellido2' => $parts[1] ?? '',
        ];
    }

    /**
     * Separar nombre completo de FUNDAE "JOSSELINE XIOMA TORRES GARCIA"
     * en nombre (primer token) y apellidos (el resto).
     * Heuristica: primer token = nombre, segundo = apellido1, resto = apellido2.
     */
    private function separarNombreCompleto(string $nombreCompleto): array
    {
        $parts = preg_split('/\s+/', trim($nombreCompleto));

        if (count($parts) <= 1) {
            return ['nombre' => $parts[0] ?? '', 'apellido1' => '', 'apellido2' => ''];
        }

        if (count($parts) === 2) {
            return ['nombre' => $parts[0], 'apellido1' => $parts[1], 'apellido2' => ''];
        }

        // Para 3+ tokens: si el primer token parece un nombre compuesto (M, MA, J, etc),
        // tomar los primeros 2 como nombre
        $primerToken = $parts[0];
        if (mb_strlen($primerToken) <= 2 && count($parts) >= 4) {
            return [
                'nombre' => $parts[0] . ' ' . $parts[1],
                'apellido1' => $parts[2],
                'apellido2' => implode(' ', array_slice($parts, 3)),
            ];
        }

        return [
            'nombre' => $parts[0],
            'apellido1' => $parts[1],
            'apellido2' => implode(' ', array_slice($parts, 2)),
        ];
    }

    private function mapearNivelEstudios(string $texto): ?int
    {
        if (!$texto) {
            return null;
        }

        foreach (self::NIVEL_ESTUDIOS as $clave => $codigo) {
            if (mb_stripos($texto, mb_substr($clave, 0, 15)) !== false) {
                return $codigo;
            }
        }

        return null;
    }

    private function mapearCategoriaProfesional(string $texto): ?int
    {
        if (!$texto) {
            return null;
        }

        foreach (self::CATEGORIA_PROFESIONAL as $clave => $codigo) {
            if (mb_stripos($texto, mb_substr($clave, 0, 10)) !== false) {
                return $codigo;
            }
        }

        return null;
    }

    private function mapearGrupoCotizacion(string $texto): ?string
    {
        if (!$texto) {
            return null;
        }

        foreach (self::GRUPO_COTIZACION as $clave => $codigo) {
            if (mb_stripos($texto, mb_substr($clave, 0, 15)) !== false) {
                return $codigo;
            }
        }

        return null;
    }
}
