<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\LegacyMappings;
use App\Models\Candidato;
use App\Models\Empresa;
use App\Models\EncomiendaAlumnoStaging;
use App\Models\EncomiendaContrato;
use App\Models\TipoCandidato;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sincroniza los contratos de encomienda firmados en el sistema externo
 * (contrato-encomienda.php) con el Panel:
 *   - Espeja cada contrato en `encomienda_contratos`.
 *   - Crea el Candidato (tipo empresa_organizadora) si la empresa existe por CIF;
 *     si no, deja el contrato en `pendiente_empresa`.
 *   - Deja los alumnos en `encomienda_alumnos_staging` (mapeados a códigos FUNDAE)
 *     para materializarlos al crear el Grupo Formativo.
 * Idempotente por `source_id`. Maneja ediciones y borrados del cliente.
 */
class SincronizarEncomienda extends Command
{
    use LegacyMappings;

    protected $signature = 'encomienda:sincronizar {--dry-run : Previsualiza sin escribir} {--force : Re-actualiza datos de contacto de candidatos ya creados}';
    protected $description = 'Sincroniza contratos de encomienda del sistema externo → candidatos + alumnos en staging';

    private bool $dryRun = false;
    private bool $force = false;

    // Contadores del resumen
    private array $stats = [
        'contratos_nuevos' => 0,
        'contratos_actualizados' => 0,
        'candidatos_creados' => 0,
        'pendientes_empresa' => 0,
        'alumnos_nuevos' => 0,
        'alumnos_actualizados' => 0,
        'alumnos_descartados' => 0,
        'errores' => 0,
    ];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->force = (bool) $this->option('force');

        if (!$this->configurarConexion()) {
            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('🧪 DRY-RUN: no se escribirá nada.');
        }

        $tablaContratos = config('encomienda.tablas.contratos', 'contratos_encomienda');
        $tablaAlumnos = config('encomienda.tablas.alumnos', 'encomienda_alumnos');

        $tipoOrganizadora = TipoCandidato::where('codigo', 'empresa_organizadora')->first();
        if (!$tipoOrganizadora) {
            $this->error('❌ No existe el TipoCandidato con código "empresa_organizadora".');
            return self::FAILURE;
        }

        $contratos = DB::connection('encomienda')->table($tablaContratos)->orderBy('id')->get();
        $this->info("📄 {$contratos->count()} contrato(s) en el origen.");

        foreach ($contratos as $c) {
            try {
                $this->procesarContrato($c, $tablaAlumnos, $tipoOrganizadora->id);
            } catch (\Throwable $e) {
                $this->stats['errores']++;
                $this->error("Contrato #{$c->id}: {$e->getMessage()}");
            }
        }

        $this->mostrarResumen();

        return self::SUCCESS;
    }

    private function procesarContrato(object $c, string $tablaAlumnos, int $tipoOrganizadoraId): void
    {
        $mirror = EncomiendaContrato::where('source_id', $c->id)->first();

        // Contrato descartado manualmente: no resucitar (solo refrescar timestamp).
        if ($mirror && $mirror->estaDescartado()) {
            if (!$this->dryRun) {
                $mirror->update(['sincronizado_en' => now()]);
            }
            $this->stats['descartados_omitidos'] = ($this->stats['descartados_omitidos'] ?? 0) + 1;
            return;
        }

        $cifNorm = $this->normalizarCifLibre($c->empresa_cif ?? null);
        $empresa = $cifNorm ? Empresa::where('cif', $cifNorm)->first() : null;

        $esNuevo = $mirror === null;

        $datosContrato = [
            'source_id'             => $c->id,
            'referencia_aceptacion' => $c->referencia_aceptacion ?? null,
            'empresa_cif'           => $cifNorm,
            'empresa_razon_social'  => $c->empresa_razon_social ?? null,
            'empresa_domicilio'     => $c->empresa_domicilio ?? null,
            'empresa_localidad'     => $c->empresa_localidad ?? null,
            'firmante_nombre'       => $c->firmante_nombre ?? null,
            'firmante_nif'          => $this->normalizarNif($c->firmante_nif ?? null),
            'firmante_cargo'        => $c->firmante_cargo ?? null,
            'email'                 => $this->limpiarEmail($c->email ?? null),
            'telefono'              => $c->telefono ?? null,
            'saldo_fundae'          => $c->saldo_fundae ?? null,
            'tiene_rlt'             => $c->tiene_rlt ?? null,
            'origen_externo'        => $c->origen ?? null,
            'estado_externo'        => $c->estado ?? null,
            'pdf_path'              => $c->pdf_path ?? null,
            'pdf_hash'              => $c->pdf_hash ?? null,
            'aceptado_en'           => $c->aceptado_en ?? null,
            'empresa_id'            => $empresa?->id,
            'sincronizado_en'       => now(),
        ];

        // Estado de procesamiento + creación de candidato
        $yaTeniaCandidato = $mirror && $mirror->candidato_id;
        if ($empresa) {
            if (!$yaTeniaCandidato) {
                $candidato = $this->crearCandidato($c, $empresa, $tipoOrganizadoraId, $datosContrato);
                if ($candidato === 'sin_email') {
                    $datosContrato['estado_procesamiento'] = 'error';
                    $datosContrato['error_message'] = 'Empresa hallada pero el contrato no trae email para crear el candidato.';
                } else {
                    $datosContrato['candidato_id'] = $candidato?->id;
                    $datosContrato['estado_procesamiento'] = $candidato ? 'candidato_creado' : $datosContrato['estado_procesamiento'] ?? 'pendiente_empresa';
                    $datosContrato['error_message'] = null;
                    if ($candidato) {
                        $this->stats['candidatos_creados']++;
                    }
                }
            } else {
                $datosContrato['estado_procesamiento'] = 'candidato_creado';
                if ($this->force && !$this->dryRun) {
                    $mirror->candidato?->update([
                        'nombre_contacto' => $c->firmante_nombre ?? $mirror->candidato->nombre_contacto,
                        'telefono'        => $c->telefono ?? $mirror->candidato->telefono,
                    ]);
                }
            }
        } else {
            $datosContrato['estado_procesamiento'] = 'pendiente_empresa';
            $this->stats['pendientes_empresa']++;
        }

        // Persistir el mirror
        if ($this->dryRun) {
            $mirror = $mirror ?? new EncomiendaContrato(['id' => 0]);
            $mirror->forceFill($datosContrato);
        } else {
            $mirror = EncomiendaContrato::updateOrCreate(['source_id' => $c->id], $datosContrato);
        }

        $esNuevo ? $this->stats['contratos_nuevos']++ : $this->stats['contratos_actualizados']++;

        // Alumnos del contrato
        $this->procesarAlumnos($c, $mirror, $tablaAlumnos);
    }

    private function crearCandidato(object $c, Empresa $empresa, int $tipoOrganizadoraId, array $datos): Candidato|string|null
    {
        $email = $this->limpiarEmail($c->email ?? null);
        if (!$email) {
            return 'sin_email';
        }

        if ($this->dryRun) {
            return null; // no se crea en dry-run; el contador de candidatos no cuenta en preview
        }

        $candidato = Candidato::create([
            'tipo_candidato_id' => $tipoOrganizadoraId,
            'empresa_id'        => $empresa->id,
            'nombre_contacto'   => $c->firmante_nombre ?: ($empresa->razon_social ?? 'Contacto'),
            'email'             => $email,
            'telefono'          => $c->telefono ?? null,
            'estatus'           => 'pendiente',
        ]);
        $candidato->inicializarRequisitos();

        return $candidato;
    }

    private function procesarAlumnos(object $c, EncomiendaContrato $mirror, string $tablaAlumnos): void
    {
        $remotos = DB::connection('encomienda')->table($tablaAlumnos)
            ->where('contrato_id', $c->id)
            ->orderBy('id')
            ->get();

        $sourceIdsVistos = [];

        foreach ($remotos as $a) {
            $sourceIdsVistos[] = $a->id;
            $apellidos = $this->separarApellidos($a->apellidos ?? null);

            $datos = [
                'encomienda_contrato_id' => $mirror->id ?: null,
                'contrato_source_id'     => $c->id,
                'referencia_contrato'    => $a->referencia_contrato ?? null,
                'nombre'                 => $this->aTitleCase($a->nombre_completo ?? null),
                'apellido1'              => $this->aTitleCase($apellidos['apellido1']),
                'apellido2'              => $this->aTitleCase($apellidos['apellido2']),
                'nif'                    => $this->normalizarNif($a->nif ?? null),
                'email'                  => $this->limpiarEmail($a->email ?? null),
                'telefono'               => $a->telefono ?? null,
                'niss'                   => $this->soloDigitos($a->numero_ss ?? null),
                'grupo_cotizacion_tgss'  => $this->mapearGrupoCotizacionEncomienda($a->grupo_cotizacion ?? null),
                'fecha_nacimiento'       => $this->normalizarFechaEncomienda($a->fecha_nacimiento ?? null),
                'sexo'                   => $this->normalizarSexo($a->sexo ?? null),
                'nivel_estudios'         => $this->mapearNivelEstudiosEncomiendaLetra($a->estudios ?? null),
                'categoria_profesional'  => $this->mapearCategoriaProfesionalEncomiendaRomano($a->grupo_profesional ?? null),
                'cargo'                  => $a->cargo ?? null,
                'curso_interes'          => $a->curso_interes ?? null,
                'horas'                  => $a->horas ?? null,
                'fecha_prevista_inicio'  => $a->fecha_prevista_inicio ?? null,
                'comentarios'            => $a->comentarios ?? null,
                'sincronizado_en'        => now(),
            ];

            $existente = EncomiendaAlumnoStaging::where('source_id', $a->id)->first();

            if ($existente && $existente->estado === 'materializado') {
                // Ya materializado: no se pisa el Alumno real. Solo se refresca metadata mínima.
                continue;
            }

            if ($existente) {
                $this->stats['alumnos_actualizados']++;
            } else {
                $this->stats['alumnos_nuevos']++;
            }

            if (!$this->dryRun) {
                EncomiendaAlumnoStaging::updateOrCreate(['source_id' => $a->id], $datos);
            }
        }

        // Borrados del cliente: filas staging pendientes que ya no vienen del origen → descartar
        $huerfanas = EncomiendaAlumnoStaging::where('contrato_source_id', $c->id)
            ->where('estado', 'pendiente')
            ->when(!empty($sourceIdsVistos), fn ($q) => $q->whereNotIn('source_id', $sourceIdsVistos))
            ->get();

        foreach ($huerfanas as $h) {
            $this->stats['alumnos_descartados']++;
            if (!$this->dryRun) {
                $h->update(['estado' => 'descartado']);
            }
        }
    }

    // ── Helpers ───────────────────────────────────────────────

    private function configurarConexion(): bool
    {
        $db = config('encomienda.db');
        if (empty($db['host']) || empty($db['username']) || empty($db['password'])) {
            $this->error('❌ Faltan credenciales ENCOMIENDA_DB_* en el .env (HOST, USERNAME, PASSWORD).');
            return false;
        }

        config(['database.connections.encomienda' => [
            'driver'    => 'mysql',
            'host'      => $db['host'],
            'port'      => $db['port'] ?? '3306',
            'database'  => $db['database'] ?? 'webcourses2014',
            'username'  => $db['username'],
            'password'  => $db['password'],
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
        ]]);

        try {
            DB::connection('encomienda')->getPdo();
        } catch (\Throwable $e) {
            $this->error('❌ No se pudo conectar al sistema externo: ' . $e->getMessage());
            return false;
        }

        return true;
    }

    /** Normalización de CIF para lookup: UPPER + sin espacios/guiones/puntos (sin validar formato estricto). */
    private function normalizarCifLibre(?string $valor): ?string
    {
        if (!$valor) {
            return null;
        }
        $norm = strtoupper(preg_replace('/[\s\-\.]+/', '', trim($valor)));
        return $norm !== '' ? $norm : null;
    }

    private function limpiarEmail(?string $valor): ?string
    {
        if (!$valor) {
            return null;
        }
        $v = strtolower(trim($valor));
        return $v !== '' ? $v : null;
    }

    private function soloDigitos(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $d = preg_replace('/\D/', '', $valor);
        return $d !== '' ? $d : null;
    }

    private function normalizarSexo(?string $valor): ?string
    {
        if (!$valor) {
            return null;
        }
        $s = strtoupper(trim($valor));
        return in_array($s, ['H', 'M'], true) ? $s : null;
    }

    /** Title Case unicode-safe (mismo criterio que MatriculacionPanel). */
    private function aTitleCase(?string $texto): ?string
    {
        if ($texto === null) {
            return null;
        }
        $texto = trim($texto);
        if ($texto === '') {
            return null;
        }
        $lower = mb_strtolower($texto, 'UTF-8');
        return preg_replace_callback(
            '/(?<=^|[\s\-\'\.])(\p{L})/u',
            fn ($m) => mb_strtoupper($m[1], 'UTF-8'),
            $lower
        );
    }

    private function mostrarResumen(): void
    {
        $this->newLine();
        $this->info('── Resumen ──');
        $this->line("Contratos nuevos:        {$this->stats['contratos_nuevos']}");
        $this->line("Contratos actualizados:  {$this->stats['contratos_actualizados']}");
        $this->line("Candidatos creados:      {$this->stats['candidatos_creados']}");
        $this->line("Pendientes de empresa:   {$this->stats['pendientes_empresa']}");
        $this->line("Alumnos nuevos (stg):    {$this->stats['alumnos_nuevos']}");
        $this->line("Alumnos actualizados:    {$this->stats['alumnos_actualizados']}");
        $this->line("Alumnos descartados:     {$this->stats['alumnos_descartados']}");
        $this->line("Contratos omitidos (descartados): " . ($this->stats['descartados_omitidos'] ?? 0));
        if ($this->stats['errores'] > 0) {
            $this->error("Errores:                 {$this->stats['errores']}");
        }
    }
}
