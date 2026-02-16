<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\EmpresaAnterior;
use App\Models\Grupo;
use App\Models\GrupoAnterior;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyData extends Command
{
    protected $signature = 'webcurso:import-legacy {--anterior : Importar a tablas del año anterior}';
    protected $description = 'Importar datos desde la base de datos legacy webcourses2014';

    public function handle()
    {
        $esAnterior = $this->option('anterior');
        
        $this->info('🚀 Iniciando importación de datos legacy...');
        
        // Configurar conexión a la base de datos legacy
        $legacyHost = env('LEGACY_DB_HOST');
        $legacyDatabase = env('LEGACY_DB_DATABASE');
        $legacyUsername = env('LEGACY_DB_USERNAME');
        $legacyPassword = env('LEGACY_DB_PASSWORD');

        if (!$legacyHost || !$legacyDatabase || !$legacyUsername || !$legacyPassword) {
            $this->error('❌ Debes definir LEGACY_DB_HOST, LEGACY_DB_DATABASE, LEGACY_DB_USERNAME y LEGACY_DB_PASSWORD en tu archivo .env');
            return 1;
        }

        config([
            'database.connections.legacy' => [
                'driver' => 'mysql',
                'host' => $legacyHost,
                'port' => '3306',
                'database' => $legacyDatabase,
                'username' => $legacyUsername,
                'password' => $legacyPassword,
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => false,
            ]
        ]);

        try {
            // Test de conexión
            DB::connection('legacy')->getPdo();
            $this->info('✅ Conexión a base de datos legacy establecida');
        } catch (\Exception $e) {
            $this->error('❌ Error al conectar con la base de datos legacy: ' . $e->getMessage());
            $this->warn('💡 Asegúrate de que la base de datos webcourses2014 existe en MySQL');
            return 1;
        }

        // Importar empresas
        $this->importarEmpresas($esAnterior);
        
        // Importar grupos
        $this->importarGrupos($esAnterior);

        $this->info('');
        $this->info('✅ Importación completada exitosamente!');
        
        return 0;
    }

    protected function importarEmpresas(bool $esAnterior)
    {
        $tablaOrigen = $esAnterior ? 'empresas_anterior' : 'empresas';
        $modeloDestino = $esAnterior ? EmpresaAnterior::class : Empresa::class;
        
        $this->info('');
        $this->info("📊 Importando empresas desde tabla: {$tablaOrigen}");

        try {
            // Verificar si la tabla existe
            $existe = DB::connection('legacy')
                ->select("SHOW TABLES LIKE '{$tablaOrigen}'");
            
            if (empty($existe)) {
                $this->warn("⚠️  La tabla {$tablaOrigen} no existe en la base de datos legacy");
                return;
            }

            $empresas = DB::connection('legacy')
                ->table($tablaOrigen)
                ->get();

            if ($empresas->isEmpty()) {
                $this->warn("⚠️  No hay datos en la tabla {$tablaOrigen}");
                return;
            }

            $bar = $this->output->createProgressBar($empresas->count());
            $bar->start();

            $importadas = 0;
            $errores = 0;

            foreach ($empresas as $empresa) {
                try {
                    $modeloDestino::updateOrCreate(
                        ['cif' => $empresa->cif],
                        [
                            'expediente' => $empresa->expediente ?? null,
                            'razon_social' => $empresa->razon_social ?? '',
                            'plantilla_media' => $empresa->plantilla_media ?? 0,
                            'reserva' => $empresa->reserva ?? null,
                            'importe_reserva_2023' => $empresa->importe_reserva_2023 ?? 0,
                            'importe_reserva_2024' => $empresa->importe_reserva_2024 ?? 0,
                            'credito_asignado' => $empresa->credito_asignado ?? 0,
                            'credito_dispuesto' => $empresa->credito_dispuesto ?? 0,
                            'credito_disponible' => $empresa->credito_disponible ?? 0,
                            'tgss' => $empresa->tgss ?? 0,
                            'cofinanciacion_privada_exigido' => $empresa->cofinanciacion_privada_exigido ?? 0,
                            'cofinanciacion_privada_cumplido' => $empresa->cofinanciacion_privada_cumplido ?? 0,
                            'cnae' => $empresa->cnae ?? null,
                            'convenio' => $empresa->convenio ?? null,
                            'pyme' => $empresa->pyme ?? 'NO',
                            'nueva_creacion' => $empresa->nueva_creacion ?? 'NO',
                            'poblacion' => $empresa->poblacion ?? null,
                            'telefono' => $empresa->telefono ?? null,
                            'email' => $empresa->email ?? null,
                            'anulada' => $empresa->anulada ?? 'NO',
                            'bloqueada' => $empresa->bloqueada ?? 'NO',
                            'nuevo' => $empresa->nuevo ?? false,
                            'fecha_creacion' => $empresa->fecha_creacion ?? now(),
                            'actualizacion' => $empresa->actualizacion ?? null,
                        ]
                    );
                    $importadas++;
                } catch (\Exception $e) {
                    $errores++;
                    $this->newLine();
                    $this->error("Error importando empresa {$empresa->cif}: " . $e->getMessage());
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("✅ Empresas importadas: {$importadas}");
            if ($errores > 0) {
                $this->warn("⚠️  Errores: {$errores}");
            }

        } catch (\Exception $e) {
            $this->error("❌ Error al importar empresas: " . $e->getMessage());
        }
    }

    protected function importarGrupos(bool $esAnterior)
    {
        $tablaOrigen = $esAnterior ? 'grupos_anterior' : 'grupos';
        $modeloDestino = $esAnterior ? GrupoAnterior::class : Grupo::class;
        
        $this->info('');
        $this->info("📊 Importando grupos desde tabla: {$tablaOrigen}");

        try {
            // Verificar si la tabla existe
            $existe = DB::connection('legacy')
                ->select("SHOW TABLES LIKE '{$tablaOrigen}'");
            
            if (empty($existe)) {
                $this->warn("⚠️  La tabla {$tablaOrigen} no existe en la base de datos legacy");
                return;
            }

            $grupos = DB::connection('legacy')
                ->table($tablaOrigen)
                ->get();

            if ($grupos->isEmpty()) {
                $this->warn("⚠️  No hay datos en la tabla {$tablaOrigen}");
                return;
            }

            // Limpiar tabla destino
            $modeloDestino::truncate();
            $this->info("🗑️  Tabla destino limpiada");

            $bar = $this->output->createProgressBar($grupos->count());
            $bar->start();

            $importados = 0;
            $errores = 0;

            foreach ($grupos as $grupo) {
                try {
                    $modeloDestino::create([
                        'grupo_id' => $grupo->grupo_id ?? null,
                        'codigo_grupo' => $grupo->codigo_grupo ?? null,
                        'codigo_grupo_accion_formativa' => $grupo->codigo_grupo_accion_formativa ?? null,
                        'tipo_accion_formativa' => $grupo->tipo_accion_formativa ?? null,
                        'denominacion' => $grupo->denominacion ?? null,
                        'cif' => $grupo->cif ?? null,
                        'inicio' => $grupo->inicio ?? null,
                        'fin' => $grupo->fin ?? null,
                        'not_inicio' => $grupo->not_inicio ?? null,
                        'not_final' => $grupo->not_final ?? null,
                        'modalidad' => $grupo->modalidad ?? null,
                        'duracion' => $grupo->duracion ?? 0,
                        'estado' => $grupo->estado ?? null,
                        'incidencia' => $grupo->incidencia ?? null,
                        'medios_formacion' => $grupo->medios_formacion ?? null,
                        'numero_participantes' => $grupo->numero_participantes ?? 0,
                        'centro_formacion' => $grupo->centro_formacion ?? null,
                        'centro_imparticion' => $grupo->centro_imparticion ?? null,
                        'centro_gestor_plataforma' => $grupo->centro_gestor_plataforma ?? null,
                    ]);
                    $importados++;
                } catch (\Exception $e) {
                    $errores++;
                    $this->newLine();
                    $this->error("Error importando grupo {$grupo->id}: " . $e->getMessage());
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("✅ Grupos importados: {$importados}");
            if ($errores > 0) {
                $this->warn("⚠️  Errores: {$errores}");
            }

        } catch (\Exception $e) {
            $this->error("❌ Error al importar grupos: " . $e->getMessage());
        }
    }
}
