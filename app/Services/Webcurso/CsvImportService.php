<?php

namespace App\Services\Webcurso;

use App\Models\Empresa;
use App\Models\EmpresaAnterior;
use App\Models\Grupo;
use App\Models\GrupoAnterior;
use App\Models\AccionFormativa;
use App\Models\ParticipanteBonificado;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

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

        $this->log('info', 'Iniciando importación de EMPRESAS...');
        $this->log('info', 'Archivo: ' . $archivo->getClientOriginalName());

        try {
            $filas = $this->leerFilas($archivo);
            $this->log('info', 'Filas detectadas: ' . count($filas));

            foreach ($filas as $data) {
                if (count($data) >= 22) {
                    $cif = $this->limpiarDato($data[1] ?? '');
                    $razon = $this->limpiarDato($data[2] ?? '');

                    if (empty($cif) && empty($razon)) {
                        $this->omitidos++;
                        continue;
                    }

                    try {
                        $this->procesarEmpresa($data, $modelo);
                        $this->procesados++;

                        if ($this->procesados <= 3) {
                            $this->log('success', "Empresa {$this->procesados}: $cif - $razon");
                        }
                    } catch (\Exception $e) {
                        $this->errores++;
                        $this->log('error', "Error en empresa: " . $e->getMessage());
                    }
                } else {
                    $contenido = implode('', $data);
                    if (!empty(trim($contenido))) {
                        $this->log('warning', 'Fila con columnas insuficientes (' . count($data) . ')');
                    }
                    $this->omitidos++;
                }
            }

            $this->log('success', "Empresas procesadas (UPSERT): {$this->procesados} registros");

        } catch (\Exception $e) {
            $this->log('error', 'Error general: ' . $e->getMessage());
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

        $this->log('info', 'Iniciando importación de GRUPOS...');
        $this->log('info', 'Archivo: ' . $archivo->getClientOriginalName());

        try {
            DB::table($tabla)->truncate();
            $this->log('info', 'Tabla grupos limpiada');

            $filas = $this->leerFilas($archivo, [5, 6, 7, 8]);
            $this->log('info', 'Filas detectadas: ' . count($filas));

            foreach ($filas as $i => $data) {
                $numFila = $i + 2; // +1 por 0-index, +1 por cabecera saltada

                if (count($data) >= 17) {
                    $grupo_id = $this->limpiarDato($data[0] ?? '');
                    $denominacion = $this->limpiarDato($data[4] ?? '');

                    if (empty($grupo_id) && empty($denominacion)) {
                        $this->omitidos++;
                        $this->log('warning', "Fila {$numFila} omitida: grupo_id y denominación vacíos");
                        continue;
                    }

                    if ($this->esNoVa($denominacion)) {
                        $this->omitidos++;
                        $this->log('warning', "Fila {$numFila} omitida (NO VA): grupo_id={$grupo_id}, denominación=" . mb_strimwidth($denominacion, 0, 80, '...'));
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
                        $this->log('error', "Error en grupo (fila {$numFila}): " . $e->getMessage());
                    }
                } else {
                    $this->omitidos++;
                    $this->log('warning', "Fila {$numFila} omitida: solo " . count($data) . " columnas (mínimo 17)");
                }
            }

            $this->log('success', "Grupos procesados: {$this->procesados} registros");
            if ($this->omitidos > 0) {
                $this->log('info', "Registros omitidos: {$this->omitidos}");
            }

        } catch (\Exception $e) {
            $this->log('error', 'Error general: ' . $e->getMessage());
        }

        return $this->getResultado();
    }

    /**
     * Importar archivo XLS de participantes FUNDAE (reemplaza todos los registros)
     */
    public function importarParticipantes(UploadedFile $archivo): array
    {
        $this->resetContadores();

        $this->log('info', '👤 Iniciando importación de PARTICIPANTES BONIFICADOS...');
        $this->log('info', 'Archivo: ' . $archivo->getClientOriginalName());

        try {
            $spreadsheet = IOFactory::load($archivo->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $maxRow = $sheet->getHighestRow();

            $this->log('info', 'Filas detectadas: ' . ($maxRow - 1) . ' (excluye cabecera)');

            // Vaciar tabla antes de importar
            DB::table('participantes_bonificados')->truncate();
            $this->log('info', '✅ Tabla participantes_bonificados limpiada');

            for ($row = 2; $row <= $maxRow; $row++) {
                $nif    = trim($sheet->getCell('B' . $row)->getValue() ?? '');
                $nombre = trim($sheet->getCell('D' . $row)->getValue() ?? '');

                // Omitir filas vacías
                if (empty($nif) && empty($nombre)) {
                    $this->omitidos++;
                    continue;
                }

                try {
                    $fechaInicio = $this->convertirFechaExcel($sheet->getCell('I' . $row)->getFormattedValue());
                    $fechaFin    = $this->convertirFechaExcel($sheet->getCell('J' . $row)->getFormattedValue());

                    ParticipanteBonificado::create([
                        'nif_participante' => $nif,
                        'niss'             => trim($sheet->getCell('C' . $row)->getValue() ?? ''),
                        'nombre'           => $nombre,
                        'estado'           => trim($sheet->getCell('E' . $row)->getValue() ?? ''),
                        'cif'              => trim($sheet->getCell('F' . $row)->getValue() ?? ''),
                        'id_codigo_grupo'  => trim($sheet->getCell('G' . $row)->getValue() ?? ''),
                        'codigo_pif'       => trim($sheet->getCell('H' . $row)->getValue() ?? ''),
                        'fecha_inicio'     => $fechaInicio,
                        'fecha_fin'        => $fechaFin,
                        'estado_grupo'     => trim($sheet->getCell('K' . $row)->getValue() ?? ''),
                    ]);

                    $this->procesados++;

                    if ($this->procesados <= 3) {
                        $this->log('success', "Participante {$this->procesados}: {$nif} - {$nombre}");
                    }
                } catch (\Exception $e) {
                    $this->errores++;
                    $this->log('error', "Error en fila {$row}: " . $e->getMessage());
                }
            }

            $this->log('success', "✅ Participantes importados: {$this->procesados} registros");
            if ($this->omitidos > 0) {
                $this->log('info', "ℹ️ Registros omitidos: {$this->omitidos}");
            }

        } catch (\Exception $e) {
            $this->log('error', '❌ Error general: ' . $e->getMessage());
        }

        return $this->getResultado();
    }

    /**
     * Convertir fecha con formato d/m/Y desde Excel
     */
    protected function convertirFechaExcel(string $fecha): ?string
    {
        if (empty(trim($fecha))) {
            return null;
        }
        try {
            $partes = explode('/', trim($fecha));
            if (count($partes) === 3) {
                return $partes[2] . '-' . str_pad($partes[1], 2, '0', STR_PAD_LEFT) . '-' . str_pad($partes[0], 2, '0', STR_PAD_LEFT);
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
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
     * Leer todas las filas de un archivo (CSV o XLS/XLSX).
     * Devuelve un array de arrays, saltando la cabecera.
     *
     * @param array<int> $columnasFecha Indices (0-based) de columnas que deben interpretarse como fechas seriales de Excel.
     */
    public function leerFilas(UploadedFile $archivo, array $columnasFecha = []): array
    {
        $extension = strtolower($archivo->getClientOriginalExtension());

        if (in_array($extension, ['xls', 'xlsx'])) {
            return $this->leerFilasXls($archivo, $columnasFecha);
        }

        return $this->leerFilasCsv($archivo);
    }

    protected function leerFilasXls(UploadedFile $archivo, array $columnasFecha = []): array
    {
        $spreadsheet = IOFactory::load($archivo->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $maxRow = $sheet->getHighestRow();
        $maxCol = $sheet->getHighestColumn();
        $filas = [];

        for ($row = 2; $row <= $maxRow; $row++) {
            $fila = [];
            $colIndex = 0;
            foreach (range('A', $maxCol) as $col) {
                $cell = $sheet->getCell($col . $row);
                $value = $cell->getValue();

                if (in_array($colIndex, $columnasFecha, true) && is_numeric($value) && $value > 25000 && $value < 60000) {
                    try {
                        $dateTime = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value);
                        $fila[] = $dateTime->format('d/m/Y');
                        $colIndex++;
                        continue;
                    } catch (\Exception $e) {
                        // cae al fallback
                    }
                }

                $fila[] = trim((string) ($value ?? ''));
                $colIndex++;
            }
            $filas[] = $fila;
        }

        return $filas;
    }

    protected function leerFilasCsv(UploadedFile $archivo): array
    {
        $handle = fopen($archivo->getRealPath(), 'r');
        if (!$handle) {
            throw new \Exception('No se pudo abrir el archivo CSV');
        }

        // Auto-detectar separador (;  o ,)
        $primeraLinea = fgets($handle);
        rewind($handle);
        $delimiter = str_contains($primeraLinea, ';') ? ';' : ',';

        // Saltar cabecera
        $headers = fgetcsv($handle, 0, $delimiter);
        if (isset($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
        }

        $filas = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $filas[] = $data;
        }
        fclose($handle);

        return $filas;
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
     * Convertir cantidad monetaria.
     * Soporta formato español (1.234,56) y formato internacional/Excel (469.51).
     */
    protected function convertirCantidad(string $cantidad): float
    {
        if (empty($cantidad) || $cantidad === '0') {
            return 0;
        }

        // Limpiar símbolo de moneda y espacios
        $cantidad = trim(str_replace(['€', "\xC2\xA0", ' '], '', $cantidad));

        $tienePunto = strpos($cantidad, '.') !== false;
        $tieneComa  = strpos($cantidad, ',') !== false;

        if ($tienePunto && $tieneComa) {
            // Formato español completo: 1.234,56 → quitar puntos de miles, coma→punto
            $cantidad = str_replace(['.', ','], ['', '.'], $cantidad);
        } elseif ($tieneComa && !$tienePunto) {
            // Formato español sin miles: 469,51 → coma→punto
            $cantidad = str_replace(',', '.', $cantidad);
        }
        // Si solo tiene punto (469.51) → ya está en formato correcto (Excel)

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
     * Convertir fecha desde formato dd/mm/yyyy o yyyy-mm-dd a Y-m-d
     */
    protected function convertirFecha(string $fecha): ?string
    {
        $fecha = trim($this->limpiarDato($fecha));
        if (empty($fecha)) {
            return null;
        }

        try {
            // Formato dd/mm/yyyy
            if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $fecha, $m)) {
                return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
            }

            // Formato yyyy-mm-dd (ya correcto)
            if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $fecha)) {
                return $fecha;
            }

            // Intentar con strtotime como fallback
            $ts = strtotime($fecha);
            return $ts && $ts > 0 ? date('Y-m-d', $ts) : null;
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
    /**
     * Importar archivo XLS de Acciones Formativas de FUNDAE
     * UPSERT por numero_accion (no truncar, tiene relaciones)
     */
    public function importarAccionesFormativas(UploadedFile $archivo): array
    {
        $this->resetContadores();

        $this->log('info', 'Iniciando importación de ACCIONES FORMATIVAS...');
        $this->log('info', 'Archivo: ' . $archivo->getClientOriginalName());

        try {
            $spreadsheet = IOFactory::load($archivo->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $maxRow = $sheet->getHighestRow();

            $this->log('info', 'Filas detectadas: ' . ($maxRow - 1) . ' (excluye cabecera)');

            for ($row = 2; $row <= $maxRow; $row++) {
                $numeroAccion = trim($sheet->getCell('A' . $row)->getValue() ?? '');
                $denominacion = trim($sheet->getCell('B' . $row)->getValue() ?? '');

                if (empty($numeroAccion) && empty($denominacion)) {
                    $this->omitidos++;
                    continue;
                }

                try {
                    AccionFormativa::updateOrCreate(
                        ['numero_accion' => (int) $numeroAccion],
                        [
                            'denominacion' => $denominacion,
                            'modalidad' => trim($sheet->getCell('C' . $row)->getValue() ?? ''),
                            'tipo' => trim($sheet->getCell('D' . $row)->getValue() ?? ''),
                            'estado' => trim($sheet->getCell('E' . $row)->getValue() ?? ''),
                            'horas' => (int) ($sheet->getCell('F' . $row)->getValue() ?? 0),
                            'nivel_formacion' => trim($sheet->getCell('G' . $row)->getValue() ?? '') ?: null,
                            'nif_proveedor_plataforma' => trim($sheet->getCell('H' . $row)->getValue() ?? '') ?: null,
                            'url_plataforma' => trim($sheet->getCell('I' . $row)->getValue() ?? '') ?: null,
                            'clave_acceso' => trim($sheet->getCell('J' . $row)->getValue() ?? '') ?: null,
                            'usuario_supervision' => trim($sheet->getCell('K' . $row)->getValue() ?? '') ?: null,
                            'area_profesional' => trim($sheet->getCell('L' . $row)->getValue() ?? '') ?: null,
                            'codigo_actividad' => trim($sheet->getCell('M' . $row)->getValue() ?? '') ?: null,
                        ]
                    );

                    $this->procesados++;

                    if ($this->procesados <= 3) {
                        $this->log('success', "Acción {$this->procesados}: #{$numeroAccion} - {$denominacion}");
                    }
                } catch (\Exception $e) {
                    $this->errores++;
                    $this->log('error', "Error en fila {$row}: " . $e->getMessage());
                }
            }

            $this->log('success', "Acciones formativas importadas: {$this->procesados} registros (UPSERT)");
            if ($this->omitidos > 0) {
                $this->log('info', "Registros omitidos: {$this->omitidos}");
            }
            if ($this->errores > 0) {
                $this->log('error', "Errores: {$this->errores}");
            }

        } catch (\Exception $e) {
            $this->log('error', 'Error general: ' . $e->getMessage());
        }

        return $this->getResultado();
    }

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
