<?php

namespace App\Console\Commands\Concerns;

trait LegacyMappings
{
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

    private const CATEGORIA_PROFESIONAL = [
        'Directivo' => 1,
        'Mando Intermedio' => 2,
        'Técnico' => 3,
        'Trabajador cualificado' => 4,
        'Trabajador con Baja cualificación' => 5,
    ];

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

    protected function mapearNivelEstudios(?string $texto): ?int
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

    protected function mapearCategoriaProfesional(?string $texto): ?int
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

    protected function mapearGrupoCotizacion(?string $texto): ?string
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

    /**
     * Separar "Escalona Alarcon" en apellido1 y apellido2.
     * Limpia sufijos legacy tipo "(REPASO)".
     */
    protected function separarApellidos(?string $lastNameCompleto): array
    {
        $lastNameCompleto = (string) $lastNameCompleto;
        $limpio = preg_replace('/\s*\(.*?\)\s*$/', '', trim($lastNameCompleto));
        $parts = preg_split('/\s+/', trim($limpio), 2);

        return [
            'apellido1' => $parts[0] ?? '',
            'apellido2' => $parts[1] ?? '',
        ];
    }

    /**
     * Separar nombre completo de FUNDAE "JOSSELINE XIOMA TORRES GARCIA"
     * en nombre, apellido1, apellido2.
     */
    protected function separarNombreCompleto(?string $nombreCompleto): array
    {
        $parts = preg_split('/\s+/', trim((string) $nombreCompleto));

        if (count($parts) <= 1) {
            return ['nombre' => $parts[0] ?? '', 'apellido1' => '', 'apellido2' => ''];
        }

        if (count($parts) === 2) {
            return ['nombre' => $parts[0], 'apellido1' => $parts[1], 'apellido2' => ''];
        }

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

    /**
     * Normaliza un CIF: TRIM + UPPER + sin espacios, guiones, puntos.
     * Devuelve null si el resultado vacío o no tiene formato CIF empresa válido.
     */
    protected function normalizarCif(?string $valor): ?string
    {
        if (!$valor) {
            return null;
        }

        $norm = strtoupper(preg_replace('/[\s\-\.]+/', '', trim($valor)));

        if ($norm === '' || !preg_match('/^[A-HJNPQRSUVW][0-9]{8}$/', $norm)) {
            return null;
        }

        return $norm;
    }

    /**
     * Normaliza una razón social para fuzzy match: TRIM + UPPER + sin puntos/comas,
     * sustituye `S L` y `SL` por `SL`, `S A` por `SA`, etc.
     */
    protected function normalizarRazonSocial(?string $valor): ?string
    {
        if (!$valor) {
            return null;
        }

        $norm = strtoupper(trim($valor));
        $norm = preg_replace('/[\.,;]+/', '', $norm);
        $norm = preg_replace('/\s+S\s*L\s*$/u', ' SL', $norm);
        $norm = preg_replace('/\s+S\s*A\s*$/u', ' SA', $norm);
        $norm = preg_replace('/\s+S\s*L\s*U\s*$/u', ' SLU', $norm);
        $norm = preg_replace('/\s+S\s*A\s*U\s*$/u', ' SAU', $norm);
        $norm = preg_replace('/\s+/', ' ', $norm);

        return trim($norm) ?: null;
    }

    /**
     * Resolución multi-fuente del CIF empresa para un registro de tbl_member.
     * Devuelve el CIF normalizado o null.
     *
     * Orden de búsqueda:
     *   1. nid si tiene formato CIF empresa
     *   2. company si tiene formato CIF empresa (registros antiguos)
     */
    protected function resolverCifLegacy(?string $nid, ?string $company): ?string
    {
        $cif = $this->normalizarCif($nid);
        if ($cif !== null) {
            return $cif;
        }

        $cif = $this->normalizarCif($company);
        if ($cif !== null) {
            return $cif;
        }

        return null;
    }

    /**
     * ¿El personal_id legacy tiene formato NIF persona válido?
     * Acepta NIF (8 dig + letra), NIE (X/Y/Z + 7 dig + letra) tras normalizar.
     */
    protected function esNifValido(?string $personalId): bool
    {
        if (!$personalId) {
            return false;
        }

        $norm = strtoupper(preg_replace('/[\s\-\.]+/', '', trim($personalId)));

        if ($norm === '' || $norm === '0') {
            return false;
        }

        if (preg_match('/^[0-9]{8}[A-Z]$/', $norm)) {
            return true;
        }

        if (preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $norm)) {
            return true;
        }

        return false;
    }

    protected function normalizarNif(?string $valor): ?string
    {
        if (!$valor) {
            return null;
        }

        $norm = strtoupper(preg_replace('/[\s\-\.]+/', '', trim($valor)));
        return $norm ?: null;
    }

    // ──────────────────────────────────────────────────────────────
    //  Mapeos PDF: textos cortos que aparecen en Fichas de Inscripción
    //  y Contratos de Encomienda. Patrones longest-first para evitar
    //  matches de prefijo. Devuelven [codigo, esInferido] donde
    //  esInferido = true para fallbacks ambiguos (UI los marca amarillo).
    // ──────────────────────────────────────────────────────────────

    /**
     * Patrones nivel estudios PDF → código FUNDAE 1-10.
     * El bool indica si el match es inferido (true = ambiguo, marcar amarillo).
     * Orden: más específico primero.
     *
     * @var array<int, array{0: string, 1: int, 2: bool}>
     */
    private const NIVEL_ESTUDIOS_PDF = [
        // Patrones exactos por código oficial
        ['menos que primaria',                    1, false],
        ['educacion primaria',                    2, false],
        ['ed primaria',                           2, false],
        ['primaria',                              2, false],
        ['1a etapa ed secundaria',                3, false],
        ['1 etapa ed secundaria',                 3, false],
        ['primera etapa',                         3, false],
        ['eso',                                   3, false],
        ['egb',                                   3, false],
        ['certif profesionalidad nivel 1',        3, false],
        ['certif profesionalidad nivel 2',        3, false],
        ['2a etapa ed secundaria',                4, false],
        ['2 etapa ed secundaria',                 4, false],
        ['segunda etapa',                         4, false],
        ['bachillerato',                          4, false],
        ['fp grado medio',                        4, false],
        ['bup',                                   4, false],
        ['fp1',                                   4, false],
        ['fp2',                                   4, false],
        ['edpostsecundaria',                      5, false],
        ['ed postsecundaria',                     5, false],
        ['certif profesionalidad nivel 3',        5, false],
        ['tecnico superior',                      6, false],
        ['tec sup',                               6, false],
        ['fp grado superior',                     6, false],
        ['fp grado sup',                          6, false],
        ['euniversitarios 1',                     7, false],
        ['e universitarios 1',                    7, false],
        ['diplomatura',                           7, false],
        ['grado',                                 7, false],
        ['euniversitarios 2',                     8, false],
        ['e universitarios 2',                    8, false],
        ['licenciatura',                          8, false],
        ['master',                                8, false],
        ['euniversitarios 3',                     9, false],
        ['e universitarios 3',                    9, false],
        ['doctorado',                             9, false],
        ['otras titulaciones',                   10, false],
        ['otros',                                10, false],
        // Fallback ambiguo: "Universitarios" sin nivel especificado → 7
        ['universitarios',                        7, true],
    ];

    /**
     * Patrones categoría profesional PDF → código FUNDAE 1-5.
     * Orden: específicas primero para evitar match de prefijo
     * (ej. "trabajador con baja cualificación" antes que "trabajador cualificado").
     *
     * @var array<int, array{0: string, 1: int}>
     */
    private const CATEGORIA_PROFESIONAL_PDF = [
        ['directivo',                       1],
        ['mando intermedio',                2],
        ['tecnico',                         3],
        ['trab con baja cualificacion',     5],
        ['trabcon baja cualificacion',      5],
        ['trabajador con baja',             5],
        ['baja cualificacion',              5],
        ['trabajador cualificado',          4],
        ['cualificado',                     4],
    ];

    /**
     * Patrones grupo cotización TGSS PDF → código '1'-'11'.
     * Orden: más específicos primero. Algunos textos pueden traer el
     * número entre paréntesis "(07) Auxiliares administrativos" — se
     * detecta aparte con regex en el parser.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const GRUPO_COTIZACION_PDF = [
        ['ingenieros licenciados',          '1'],
        ['ingenieros y licenciados',        '1'],
        ['alta direccion',                  '1'],
        ['ingenieros tecnicos',             '2'],
        ['peritos',                         '2'],
        ['ayudantes titulados',             '2'],
        ['jefes administrativos',           '3'],
        ['jefes de taller',                 '3'],
        ['ayudantes no titulados',          '4'],
        ['oficiales administrativos',       '5'],
        ['subalternos',                     '6'],
        ['auxiliares administrativos',      '7'],
        ['auxiliares administ',             '7'],
        ['oficiales de primera',            '8'],
        ['oficiales de 1',                  '8'],
        ['oficiales de tercera',            '9'],
        ['oficiales de 3',                  '9'],
        ['especialistas',                   '9'],
        ['trabajadores mayores de 18',     '10'],
        ['peones',                         '10'],
        ['trabajadores menores de 18',     '11'],
        ['trabajadores menos de 18',       '11'],
        ['menores de 18',                  '11'],
    ];

    /**
     * Normaliza texto para matching: minúsculas, sin acentos, sin puntuación
     * común, espacios colapsados. Usado por los mapeos PDF.
     */
    protected function normalizarTextoMapeo(?string $texto): string
    {
        if (!$texto) {
            return '';
        }

        $t = trim($texto);
        // Smalot puede devolver nombres de campo AcroForm en Latin-1 / Windows-1252
        // (no UTF-8) cuando contienen acentos. Convertimos a UTF-8 si detectamos
        // codificación inválida — sin esto la "ó" llega como byte 0xF3 huérfano y
        // mb_strtolower / strtr fallan.
        if (!mb_check_encoding($t, 'UTF-8')) {
            $t = mb_convert_encoding($t, 'UTF-8', 'Windows-1252');
        }
        $t = mb_strtolower($t);
        // Strip acentos y superíndices ordinales españoles (ª/º).
        $t = strtr($t, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n', 'ç' => 'c',
            'ª' => 'a', 'º' => 'o',
        ]);
        // Eliminar el carácter de reemplazo Unicode (U+FFFD) por si queda
        // alguno tras la conversión de encoding.
        $t = preg_replace('/[\x{FFFD}]+/u', '', $t);
        // Quitar puntos, comas, guiones, paréntesis; colapsar espacios.
        $t = preg_replace('/[\.,;\-\(\)\/]+/', ' ', $t);
        $t = preg_replace('/\s+/', ' ', $t);
        return trim($t);
    }

    /**
     * Mapea texto corto del PDF a código nivel estudios 1-10.
     * Devuelve [codigo, esInferido]. esInferido=true para fallback ambiguo.
     */
    protected function mapearNivelEstudiosPdf(?string $texto): array
    {
        $norm = $this->normalizarTextoMapeo($texto);
        if ($norm === '') {
            return [null, false];
        }

        foreach (self::NIVEL_ESTUDIOS_PDF as [$patron, $codigo, $esInferido]) {
            if (mb_strpos($norm, $patron) !== false) {
                return [$codigo, $esInferido];
            }
        }

        return [null, false];
    }

    /**
     * Mapea texto corto del PDF a código categoría profesional 1-5.
     */
    protected function mapearCategoriaProfesionalPdf(?string $texto): ?int
    {
        $norm = $this->normalizarTextoMapeo($texto);
        if ($norm === '') {
            return null;
        }

        foreach (self::CATEGORIA_PROFESIONAL_PDF as [$patron, $codigo]) {
            if (mb_strpos($norm, $patron) !== false) {
                return $codigo;
            }
        }

        return null;
    }

    /**
     * Mapea texto corto del PDF a código grupo cotización TGSS 1-11.
     * Si el texto incluye "(NN)" extrae el número directamente.
     */
    protected function mapearGrupoCotizacionPdf(?string $texto): ?string
    {
        if (!$texto) {
            return null;
        }

        // Caso "(07) Auxiliares administrativos" o "(7)"
        if (preg_match('/\(\s*0?(\d{1,2})\s*\)/', $texto, $m) && (int) $m[1] >= 1 && (int) $m[1] <= 11) {
            return (string) (int) $m[1];
        }

        $norm = $this->normalizarTextoMapeo($texto);
        if ($norm === '') {
            return null;
        }

        foreach (self::GRUPO_COTIZACION_PDF as [$patron, $codigo]) {
            if (mb_strpos($norm, $patron) !== false) {
                return $codigo;
            }
        }

        return null;
    }
}
