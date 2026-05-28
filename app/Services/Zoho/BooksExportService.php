<?php

namespace App\Services\Zoho;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BooksExportService
{
    private const CABECERAS = [
        'Participante', 'NIF', 'Estado', 'Grupo FUNDAE',
        'Fecha inicio', 'Fecha fin', 'Empresa', 'CIF',
        'Tel. Empresa Panel', 'Tel. Contacto Zoho', 'Nombre Contacto Zoho',
        'Email Empresa Panel', 'Email Contacto Zoho',
        'Total impagado', 'Nº facturas', 'Facturas',
    ];

    /**
     * Genera el XLSX de impagados y lo escribe directamente a php://output.
     * Pensado para usarse dentro de un Closure de response()->streamDownload().
     *
     * @param array<int,array<string,mixed>> $impagados Filas con el contrato de BooksIndex::cargarImpagados.
     */
    public function streamImpagadosXlsx(array $impagados): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Impagados');

        $sheet->fromArray([self::CABECERAS], null, 'A1');

        // Por cada columna, define el tipo de dato. Usamos TYPE_STRING para todo
        // lo que pueda contener símbolos (CIF, NIF, teléfonos con +34, emails)
        // para que Excel no haga coerciones (ej: +34662069270 → 34662069270).
        // Solo N (total impagado) y O (Nº facturas) se escriben como numéricos.
        $cols = [
            'A' => DataType::TYPE_STRING,  // Participante
            'B' => DataType::TYPE_STRING,  // NIF
            'C' => DataType::TYPE_STRING,  // Estado
            'D' => DataType::TYPE_STRING,  // Grupo FUNDAE
            'E' => DataType::TYPE_STRING,  // Fecha inicio (string dd/mm/yyyy)
            'F' => DataType::TYPE_STRING,  // Fecha fin
            'G' => DataType::TYPE_STRING,  // Empresa
            'H' => DataType::TYPE_STRING,  // CIF
            'I' => DataType::TYPE_STRING,  // Tel Empresa Panel
            'J' => DataType::TYPE_STRING,  // Tel Contacto Zoho (puede empezar con +)
            'K' => DataType::TYPE_STRING,  // Nombre Contacto Zoho
            'L' => DataType::TYPE_STRING,  // Email Empresa Panel
            'M' => DataType::TYPE_STRING,  // Email Contacto Zoho
            'N' => DataType::TYPE_NUMERIC, // Total impagado
            'O' => DataType::TYPE_NUMERIC, // Nº facturas
            'P' => DataType::TYPE_STRING,  // Facturas
        ];

        $fila = 2;
        foreach ($impagados as $i) {
            $telPanel = $this->primerValorPorFuente($i['telefonos'] ?? [], 'Empresa Panel', 'raw');
            $telZoho  = $this->todosLosValoresPorFuente($i['telefonos'] ?? [], 'Contacto Zoho', 'raw');
            $emPanel  = $this->primerValorPorFuente($i['emails']    ?? [], 'Empresa Panel', 'email');
            $emZoho   = $this->primerValorPorFuente($i['emails']    ?? [], 'Contacto Zoho', 'email');
            $nomZoho  = $this->primerNombreDelContactoZoho($i['telefonos'] ?? [], $i['emails'] ?? []);
            $facts    = collect($i['facturas'] ?? [])->pluck('invoice_number')->filter()->implode('; ');

            $valores = [
                'A' => $i['nombre']           ?? '',
                'B' => $i['nif_participante'] ?? '',
                'C' => $i['estado']           ?? '',
                'D' => $i['id_codigo_grupo']  ?? '',
                'E' => $i['fecha_inicio']     ?? '',
                'F' => $i['fecha_fin']        ?? '',
                'G' => $i['razon_social']     ?? '',
                'H' => $i['cif']              ?? '',
                'I' => $telPanel,
                'J' => $telZoho,
                'K' => $nomZoho,
                'L' => $emPanel,
                'M' => $emZoho,
                'N' => (float) ($i['total_impagado'] ?? 0),
                'O' => (int)   ($i['num_facturas']   ?? 0),
                'P' => $facts,
            ];

            foreach ($valores as $col => $val) {
                $sheet->getCell("{$col}{$fila}")->setValueExplicit($val, $cols[$col]);
            }

            $fila++;
        }

        $this->aplicarFormato($sheet, count($impagados) + 1);

        (new Xlsx($spreadsheet))->save('php://output');

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    private function primerValorPorFuente(array $items, string $fuente, string $key): string
    {
        foreach ($items as $it) {
            if (($it['fuente'] ?? null) === $fuente && ! empty($it[$key] ?? null)) {
                return (string) $it[$key];
            }
        }
        return '';
    }

    private function todosLosValoresPorFuente(array $items, string $fuente, string $key): string
    {
        $vals = [];
        foreach ($items as $it) {
            if (($it['fuente'] ?? null) === $fuente && ! empty($it[$key] ?? null)) {
                $vals[] = (string) $it[$key];
            }
        }
        return implode('; ', array_values(array_unique($vals)));
    }

    private function primerNombreDelContactoZoho(array $telefonos, array $emails): string
    {
        foreach ([$telefonos, $emails] as $coleccion) {
            foreach ($coleccion as $it) {
                if (($it['fuente'] ?? null) === 'Contacto Zoho' && ! empty($it['nombre'] ?? null)) {
                    return (string) $it['nombre'];
                }
            }
        }
        return '';
    }

    private function aplicarFormato(Worksheet $sheet, int $totalFilas): void
    {
        $ultimaCol = 'P'; // 16 columnas: A..P

        // Cabecera: negrita + fondo gris claro + alineado al centro
        $headerRange = "A1:{$ultimaCol}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E5E7EB');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Freeze fila 1
        $sheet->freezePane('A2');

        // Auto-size en todas las columnas
        foreach (range('A', $ultimaCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Total impagado (columna N) → número con 2 decimales
        if ($totalFilas > 1) {
            $sheet->getStyle("N2:N{$totalFilas}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');
        }
    }
}
