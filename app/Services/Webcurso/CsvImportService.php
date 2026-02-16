<?php

namespace App\Services\Webcurso;

use App\Models\Empresa;
use App\Models\EmpresaAnterior;
use App\Models\Grupo;
use App\Models\GrupoAnterior;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CsvImportService
{
    protected array $logs = [];
    protected int $procesados = 0;
    protected int $errores = 0;
    protected int $omitidos = 0;

    /**
     * Importar archivo CSV de empresas
     */
    public function importarEmpresas(UploadedFile $archivo, bool $esAnterior = false): array
    {
        $this->resetContadores();
        $modelo = $esAnterior ? EmpresaAnterior::class : Empresa::class;
        
        $this->log('info', '🏢 Iniciando importación de EMPRESAS...');
        $this->log('info', 'Archivo: ' . $archivo->getClientOriginalName());

        try {
            $handle = fopen($archivo->getRealPath(), 'r');
            if (!$handle) {
                throw new \Exception('No se pudo abrir el archivo');
            }

            // Leer headers
            $headers = $this->leerHeaders($handle);
            $this->log('info', 'Headers detectados: ' . count($headers) . ' columnas');

            // Procesar cada línea
            while (($data = fgetcsv($handle, 0, ';')) !== false) {
                if (count($data) >= 22) {
                    try {
                        $this->procesarEmpresa($data, $modelo);
                        $this->procesados++;
                        
                        if ($this->procesados <= 3) {
                            $this->log('success', "Empresa {$this->procesados}: " . $this->limpiarDato($data[1]) . ' - ' . $this->limpiarDato($data[2]));
                        }
                    } catch (\Exception $e) {
                        $this->errores++;
                        $this->log('error', "Error en empresa: " . $e->getMessage());
                    }
                } else {
                    $this->omitidos++;
                    $this->log('warning', 'Empresa con columnas insuficientes: ' . count($data));
                }
            }

            fclose($handle);
            $this->log('success', "✅ Empresas procesadas (UPSERT): {$this->procesados} registros");

        } catch (\Exception $e) {
            $this->log('error', '❌ Error general: ' . $e->getMessage());
        }

        return $this->getResultado();
    }

    /**
     * Importar archivo CSV de grupos
     */
    public function importarGrupos(UploadedFile $archivo, bool $esAnterior = false): array
    {
        $this->resetContadores();
        $modelo = $esAnterior ? GrupoAnterior::class : Grupo::class;
        $tabla = $esAnterior ? 'grupos_anterior' : 'grupos';

        $this->log('info', '👥 Iniciando importación de GRUPOS...');
        $this->log('info', 'Archivo: ' . $archivo->getClientOriginalName());

        try {
            // Limpiar tabla antes de importar
            DB::table($tabla)->truncate();
            $this->log('info', '✅ Tabla grupos limpiada');

            $handle = fopen($archivo->getRealPath(), 'r');
            if (!$handle) {
                throw new \Exception('No se pudo abrir el archivo');
            }

            // Leer headers
            $headers = $this->leerHeaders($handle);
            $this->log('info', 'Headers detectados: ' . count($headers) . ' columnas');

            // Procesar cada línea
            while (($data = fgetcsv($handle, 0, ';')) !== false) {
                if (count($data) >= 17) {
                    $grupo_id = $this->limpiarDato($data[0] ?? '');
                    $denominacion = $this->limpiarDato($data[4] ?? '');
                    
                    // 1. Ignorar líneas totalmente vacías (sin ID y sin Denominación)
                    if (empty($grupo_id) && empty($denominacion)) {
                        $this->omitidos++;
                        continue;
                    }

                    // 2. Ignorar registros "NO VA" (por petición expresa del usuario)
                    if ($this->esNoVa($denominacion)) {
                        $this->omitidos++;
                        continue;
                    }

                    try {
                        $this->procesarGrupo($data, $modelo);
                        $this->procesados++;
                        
                        if ($this->procesados <= 3) {
                            $cif = $this->extraerCif($denominacion);
                            $this->log('success', "Grupo {$this->procesados}: CIF=$cif");
                        }
                    } catch (\Exception $e) {
                        $this->errores++;
                        $this->log('error', "Error en grupo: " . $e->getMessage());
                    }
                } else {
                    $this->omitidos++;
                }
            }

            fclose($handle);
            $this->log('success', "✅ Grupos procesados: {$this->procesados} registros");
            if ($this->omitidos > 0) {
                $this->log('info', "ℹ️ Registros omitidos: {$this->omitidos}");
            }

        } catch (\Exception $e) {
            $this->log('error', '❌ Error general: ' . $e->getMessage());
        }

        return $this->getResultado();
    }

    /**
     * Procesar una fila de empresa
     */
    protected function procesarEmpresa(array $data, string $modelo): void
    {
        $cif = $this->limpiarDato($data[1]);
        if (empty($cif)) {
            throw new \Exception('CIF vacío');
        }

        $datos = [
            'expediente' => $this->limpiarDato($data[0]),
            'cif' => $cif,
            'razon_social' => $this->limpiarDato($data[2]),
            'plantilla_media' => (int) $this->limpiarDato($data[3]),
            'reserva' => $this->limpiarDato($data[4]),
            'importe_reserva_2023' => $this->convertirCantidad($data[5] ?? '0'),
            'importe_reserva_2024' => $this->convertirCantidad($data[6] ?? '0'),
            'credito_asignado' => $this->convertirCantidad($data[7] ?? '0'),
            'credito_dispuesto' => $this->convertirCantidad($data[8] ?? '0'),
            'credito_disponible' => $this->convertirCantidad($data[9] ?? '0'),
            'tgss' => $this->convertirCantidad($data[10] ?? '0'),
            'cofinanciacion_privada_exigido' => $this->convertirPorcentaje($data[11] ?? '0'),
            'cofinanciacion_privada_cumplido' => $this->convertirPorcentaje($data[12] ?? '0'),
            'cnae' => $this->limpiarDato($data[13] ?? ''),
            'convenio' => substr($this->limpiarDato($data[14] ?? ''), 0, 490),
            'pyme' => strtoupper($this->limpiarDato($data[15] ?? 'NO')),
            'nueva_creacion' => strtoupper($this->limpiarDato($data[16] ?? 'NO')),
            'poblacion' => $this->limpiarDato($data[17] ?? ''),
            'telefono' => $this->limpiarDato($data[18] ?? ''),
            'email' => $this->limpiarDato($data[19] ?? ''),
            'anulada' => strtoupper($this->limpiarDato($data[20] ?? 'NO')),
            'bloqueada' => strtoupper($this->limpiarDato($data[21] ?? 'NO')),
        ];

        // UPSERT: actualizar si existe, crear si no
        $empresa = $modelo::where('cif', $cif)->first();
        
        if ($empresa) {
            // Verificar si hay cambios
            $hayCambios = false;
            foreach ($datos as $key => $value) {
                if ($empresa->$key != $value) {
                    $hayCambios = true;
                    break;
                }
            }
            
            if ($hayCambios) {
                $datos['nuevo'] = false;
                $datos['actualizacion'] = now();
                $empresa->update($datos);
            }
        } else {
            $datos['nuevo'] = true;
            $datos['fecha_creacion'] = now();
            $modelo::create($datos);
        }
    }

    /**
     * Procesar una fila de grupo
     */
    protected function procesarGrupo(array $data, string $modelo): void
    {
        $denominacion = $this->limpiarDato($data[4] ?? '');
        $cif = $this->extraerCif($denominacion);

        $datos = [
            'grupo_id' => $this->limpiarDato($data[0] ?? ''),
            'codigo_grupo' => $this->limpiarDato($data[1] ?? ''),
            'codigo_grupo_accion_formativa' => $this->limpiarDato($data[2] ?? ''),
            'tipo_accion_formativa' => $this->limpiarDato($data[3] ?? ''),
            'denominacion' => $denominacion,
            'cif' => $cif,
            'inicio' => $this->convertirFecha($data[5] ?? ''),
            'fin' => $this->convertirFecha($data[6] ?? ''),
            'not_inicio' => $this->convertirFecha($data[7] ?? ''),
            'not_final' => $this->convertirFecha($data[8] ?? ''),
            'modalidad' => $this->limpiarDato($data[9] ?? ''),
            'duracion' => (int) $this->limpiarDato($data[10] ?? '0'),
            'estado' => $this->limpiarDato($data[11] ?? ''),
            'incidencia' => $this->limpiarDato($data[12] ?? ''),
            'medios_formacion' => $this->limpiarDato($data[13] ?? ''),
            'numero_participantes' => (int) $this->limpiarDato($data[14] ?? '0'),
            'centro_formacion' => $this->limpiarDato($data[15] ?? ''),
            'centro_imparticion' => $this->limpiarDato($data[16] ?? ''),
            'centro_gestor_plataforma' => $this->limpiarDato($data[17] ?? ''),
        ];

        $modelo::create($datos);
    }

    /**
     * Leer headers del CSV
     */
    protected function leerHeaders($handle): array
    {
        $headers = fgetcsv($handle, 0, ';');
        if (isset($headers[0])) {
            // Quitar BOM si existe
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }
        return $headers ?: [];
    }

    /**
     * Limpiar dato del CSV
     */
    protected function limpiarDato(string $dato): string
    {
        $dato = trim(str_replace(['"', "'"], '', $dato));
        if (!mb_check_encoding($dato, 'UTF-8')) {
            $dato = mb_convert_encoding($dato, 'UTF-8', 'ISO-8859-1');
        }
        return $dato;
    }

    /**
     * Convertir cantidad monetaria
     */
    protected function convertirCantidad(string $cantidad): float
    {
        if (empty($cantidad) || $cantidad === '0') {
            return 0;
        }
        $cantidad = str_replace(['.', ','], ['', '.'], $cantidad);
        return (float) $cantidad;
    }

    /**
     * Convertir porcentaje
     */
    protected function convertirPorcentaje(string $porcentaje): float
    {
        if (empty($porcentaje)) {
            return 0;
        }
        $porcentaje = str_replace(['%', ','], ['', '.'], $porcentaje);
        return (float) $porcentaje;
    }

    /**
     * Convertir fecha
     */
    protected function convertirFecha(string $fecha): ?string
    {
        if (empty($fecha)) {
            return null;
        }
        try {
            return date('Y-m-d', strtotime(str_replace('/', '-', $this->limpiarDato($fecha))));
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Verificar si es "NO VA"
     */
    protected function esNoVa(string $denominacion): bool
    {
        $denominacion = strtoupper(trim($denominacion));
        return strpos($denominacion, 'NO VA') === 0;
    }

    /**
     * Extraer CIF de denominación
     */
    protected function extraerCif(string $denominacion): string
    {
        if (preg_match('/^([A-Za-z0-9]+)\s/', trim($denominacion), $matches)) {
            $cif = $matches[1];
            if (strlen($cif) >= 8) {
                return $cif;
            }
        }
        return '';
    }

    /**
     * Agregar log
     */
    protected function log(string $tipo, string $mensaje): void
    {
        $this->logs[] = ['tipo' => $tipo, 'mensaje' => $mensaje];
        Log::channel('single')->info("[WebCurso Import] [$tipo] $mensaje");
    }

    /**
     * Resetear contadores
     */
    protected function resetContadores(): void
    {
        $this->logs = [];
        $this->procesados = 0;
        $this->errores = 0;
        $this->omitidos = 0;
    }

    /**
     * Obtener resultado de la importación
     */
    protected function getResultado(): array
    {
        return [
            'logs' => $this->logs,
            'procesados' => $this->procesados,
            'errores' => $this->errores,
            'omitidos' => $this->omitidos,
        ];
    }
}
