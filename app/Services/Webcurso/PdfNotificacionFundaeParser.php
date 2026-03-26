<?php

namespace App\Services\Webcurso;

use App\Models\Grupo;
use Smalot\PdfParser\Parser;

/**
 * Parsea la "Notificación: Inicio de Grupo" descargada del portal FUNDAE.
 * Extrae los campos necesarios para crear el registro en la tabla `grupos`
 * y validar la correspondencia con el GrupoFormativo del Panel.
 */
class PdfNotificacionFundaeParser
{
    private array $meses = [
        'enero' => '01', 'febrero' => '02', 'marzo' => '03',
        'abril' => '04', 'mayo' => '05', 'junio' => '06',
        'julio' => '07', 'agosto' => '08', 'septiembre' => '09',
        'octubre' => '10', 'noviembre' => '11', 'diciembre' => '12',
    ];

    public function parsear(string $filePath): array
    {
        $parser = new Parser();
        $pdf    = $parser->parseFile($filePath);
        $text   = $pdf->getText();

        // Normalizar espacios múltiples pero conservar saltos de línea
        $text = preg_replace('/[^\S\n]+/', ' ', $text);

        return $this->extraerDatos($text);
    }

    protected function extraerDatos(string $text): array
    {
        $datos = [];

        // ID Grupo FUNDAE (p.ej. 335629)
        if (preg_match('/ID\s+Grupo\s+(\d+)/iu', $text, $m)) {
            $datos['grupo_id'] = trim($m[1]);
        }

        // Número de la Acción Formativa (p.ej. 181)
        if (preg_match('/Acci[oó]n\s+Formativa\s+(\d+)/iu', $text, $m)) {
            $datos['numero_accion'] = (int) $m[1];
        }

        // Número de Grupo / id_grupo_fundae (p.ej. 1)
        // "Grupo 1" sin "ID" delante
        if (preg_match('/(?:^|\n)\s*Grupo\s+(\d+)\s*(?:\n|$)/ium', $text, $m)) {
            $datos['id_grupo_fundae'] = (int) $m[1];
        }

        // Denominación (texto libre hasta el siguiente encabezado en mayúsculas)
        if (preg_match('/Denominaci[oó]n\s+(.+?)(?=\n[A-ZÁÉÍÓÚÑ ]{4,}|\z)/uis', $text, $m)) {
            $datos['denominacion'] = trim(preg_replace('/\s+/', ' ', $m[1]));
        }

        // CIF extraído de la denominación
        if (!empty($datos['denominacion'])) {
            $datos['cif'] = Grupo::extraerCif($datos['denominacion']);
        }

        // Fecha inicio / fin
        if (preg_match('/Fecha\s+inicio\s+(\d{2}\/\d{2}\/\d{4})/iu', $text, $m)) {
            $datos['inicio'] = $this->convertirFecha($m[1]);
        }
        if (preg_match('/Fecha\s+fin\s+(\d{2}\/\d{2}\/\d{4})/iu', $text, $m)) {
            $datos['fin'] = $this->convertirFecha($m[1]);
        }

        // Fecha de emisión → not_inicio (p.ej. "Emitido el lunes 23 de marzo de 2026")
        if (preg_match('/Emitido\s+el\s+\w+\s+(\d+)\s+de\s+(\w+)\s+de\s+(\d{4})/iu', $text, $m)) {
            $mes = $this->meses[mb_strtolower($m[2])] ?? null;
            if ($mes) {
                $datos['not_inicio'] = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $mes, (int) $m[1]);
            }
        }

        // Horas totales
        if (preg_match('/Horas\s+totales\s+(\d+)/iu', $text, $m)) {
            $datos['duracion'] = (int) $m[1];
        }

        // Número de participantes
        if (preg_match('/participantes\s+previstos\s+para\s+este\s+grupo\s+es\s+(\d+)/iu', $text, $m)) {
            $datos['numero_participantes'] = (int) $m[1];
        }

        // Siempre Teleformación y Válido tras la notificación de inicio
        $datos['modalidad'] = 'Teleformación';
        $datos['estado']    = 'Válido';

        return $datos;
    }

    protected function convertirFecha(string $fecha): string
    {
        [$d, $m, $y] = explode('/', $fecha);
        return sprintf('%04d-%02d-%02d', (int) $y, (int) $m, (int) $d);
    }
}
