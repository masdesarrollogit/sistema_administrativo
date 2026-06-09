<?php

return [
    /*
     | Activa el Tier 3 (OCR local con Tesseract) en el pipeline de
     | extracción de Fichas/Encomiendas. Si está desactivado, los PDFs
     | escaneados/imagen caen directamente a "ilegible → manual".
     */
    'enabled' => env('PDF_OCR_ENABLED', true),

    /*
     | DPI con el que se renderizan las páginas del PDF a PNG antes de
     | pasarlas a Tesseract. 300 da buena calidad de OCR sin gastar memoria.
     | Subir a 400-600 mejora marginalmente para escaneos malos, baja a 200
     | para PDFs muy nítidos y ganar velocidad.
     */
    'dpi' => (int) env('PDF_OCR_DPI', 300),

    /*
     | Idiomas Tesseract a aplicar (formato `lang1+lang2`). Necesario tener
     | los paquetes APT instalados: tesseract-ocr-spa, tesseract-ocr-eng.
     */
    'languages' => env('PDF_OCR_LANG', 'spa+eng'),

    /*
     | Page Segmentation Mode de Tesseract. 6 = Assume a single uniform block
     | of text. Funciona bien para formularios tipo Ficha/Encomienda. Otros:
     | 3 (default, full layout analysis), 4 (single column), 11 (sparse text).
     */
    'psm' => (int) env('PDF_OCR_PSM', 6),

    /*
     | Tope de páginas a procesar por PDF. Evita gastar minutos en PDFs de
     | 50+ páginas erróneamente subidos. En Encomienda procesamos solo la 2,
     | en Ficha la 1, en desconocido hasta este número.
     */
    'max_pages' => (int) env('PDF_OCR_MAX_PAGES', 3),

    /*
     | Umbral mínimo de caracteres en el texto OCR para considerarlo útil.
     | Por debajo, asumimos que el OCR no produjo nada significativo
     | (escaneos a baja resolución, manuscritos) y caemos a ilegible.
     */
    'min_chars' => (int) env('PDF_OCR_MIN_CHARS', 200),

    /*
     | Carpeta para PNG temporales generados desde Imagick. Se borran tras
     | el OCR. Vacío = sys_get_temp_dir().
     */
    'tmp_dir' => env('PDF_OCR_TMP_DIR', ''),
];
