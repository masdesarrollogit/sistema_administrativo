<?php

namespace App\Services\Webcurso;

use Imagick;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Throwable;

/**
 * Tier 3 del pipeline de extracción de PDFs: renderiza las páginas del PDF
 * como PNG vía Imagick y aplica OCR con Tesseract local (idioma `spa+eng`).
 *
 * Retorna el texto OCR concatenado. La lógica de parseo (identificar campos
 * por labels) la hace el caller — típicamente PdfFichaInscripcionParser
 * reutilizando su método `extraerCamposTextoPlano()`.
 *
 * Es fail-soft: si Tesseract no está disponible o Imagick falla, devuelve
 * cadena vacía y el caller cae a "ilegible → manual entry" sin romper nada.
 */
class PdfOcrExtractor
{
    public function extraerTexto(string $pdfPath, string $tipo = 'desconocido'): string
    {
        if (!is_file($pdfPath) || !is_readable($pdfPath)) {
            return '';
        }
        if (!class_exists(TesseractOCR::class) || !class_exists(Imagick::class)) {
            return '';
        }
        if (!$this->tesseractDisponible()) {
            return '';
        }

        $tmpDir = $this->resolverTmpDir();
        $imagenes = [];

        try {
            $imagenes = $this->renderizarPaginas($pdfPath, $tipo, $tmpDir);
            if (empty($imagenes)) {
                return '';
            }

            $textos = [];
            foreach ($imagenes as $img) {
                $textos[] = $this->ocrPagina($img);
            }
            return trim(implode("\n\n", array_filter($textos)));
        } catch (Throwable $e) {
            report($e);
            return '';
        } finally {
            foreach ($imagenes as $img) {
                @unlink($img);
            }
        }
    }

    /**
     * Renderiza las páginas relevantes del PDF a PNG y devuelve sus rutas.
     */
    private function renderizarPaginas(string $pdfPath, string $tipo, string $tmpDir): array
    {
        $dpi = (int) config('pdf_ocr.dpi', 300);
        $maxPaginas = (int) config('pdf_ocr.max_pages', 3);

        // Determinar índices de páginas a procesar
        $paginasAProcesar = match ($tipo) {
            'encomienda' => [1],         // página 2 (0-indexed)
            'ficha'      => [0],         // página 1
            default      => range(0, $maxPaginas - 1),
        };

        $rutas = [];
        $base = uniqid('ocr_', true);

        foreach ($paginasAProcesar as $idx) {
            try {
                $im = new Imagick();
                $im->setResolution($dpi, $dpi);

                // Imagick lanza WandException si la página no existe (PDF
                // más corto que el índice). Capturamos y saltamos.
                $im->readImage($pdfPath . '[' . $idx . ']');
                $im->setImageFormat('png');

                // Mejora la legibilidad para Tesseract: escala de grises +
                // contraste. Mantiene el tamaño razonable.
                $im->setImageType(Imagick::IMGTYPE_GRAYSCALE);
                $im->setImageDepth(8);

                $ruta = $tmpDir . DIRECTORY_SEPARATOR . $base . '_p' . $idx . '.png';
                $im->writeImage($ruta);
                $im->clear();
                $im->destroy();

                $rutas[] = $ruta;
            } catch (Throwable) {
                continue;  // página no existe o renderiza mal, saltar
            }
        }

        return $rutas;
    }

    private function ocrPagina(string $imagenPath): string
    {
        try {
            $tess = (new TesseractOCR($imagenPath))
                ->lang(...explode('+', (string) config('pdf_ocr.languages', 'spa+eng')))
                ->psm((int) config('pdf_ocr.psm', 6));

            return (string) $tess->run();
        } catch (Throwable $e) {
            report($e);
            return '';
        }
    }

    private function tesseractDisponible(): bool
    {
        // Comprobación barata: ejecutar `tesseract --version` y mirar el
        // status. Cacheable, pero el coste por llamada es ~10ms.
        $output = [];
        $status = 0;
        @exec('tesseract --version 2>&1', $output, $status);
        return $status === 0;
    }

    private function resolverTmpDir(): string
    {
        $cfg = (string) config('pdf_ocr.tmp_dir', '');
        $dir = $cfg !== '' ? $cfg : sys_get_temp_dir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return rtrim($dir, DIRECTORY_SEPARATOR);
    }
}
