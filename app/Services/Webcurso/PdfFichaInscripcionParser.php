<?php

namespace App\Services\Webcurso;

use App\Console\Commands\Concerns\LegacyMappings;
use App\Services\Webcurso\PdfOcrExtractor;
use Carbon\Carbon;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Parsea una Ficha de Inscripción (1 página) o un Contrato de Encomienda
 * WebCurso 2026 (3 páginas, datos del alumno en página 2) y extrae los
 * campos del alumno listo para incorporar al grupo formativo.
 *
 * Soporta dos formatos de origen de los valores:
 *   1) PDFs AcroForm rellenados digitalmente: los valores están en los
 *      appearance streams (XObject/Form). Se extraen y se asignan a cada
 *      campo por pattern matching (formato/valor).
 *   2) PDFs con capa de texto real (no AcroForm): los valores están en el
 *      content stream y se extraen con getText() de cada página.
 *
 * Los PDFs de pura imagen (escaneos sin OCR, manuscritos) caen en 'ilegible'.
 *
 * Retorna:
 *  [
 *    'exito'      => bool,
 *    'tipo'       => 'ficha'|'encomienda'|'ilegible',
 *    'datos'      => [...],  // campos del alumno (claves siempre presentes)
 *    'inferidos'  => [...],  // claves con valor por fallback ambiguo
 *    'faltantes'  => [...],  // claves obligatorias sin valor extraído
 *    'avisos'     => [...],  // mensajes informativos / advertencias
 *  ]
 */
class PdfFichaInscripcionParser
{
    use LegacyMappings;

    private const CAMPOS_REQUERIDOS = [
        'nombre', 'apellido1', 'nif', 'email',
    ];

    private const TODOS_LOS_CAMPOS = [
        'nombre', 'apellido1', 'apellido2',
        'nif', 'email', 'telefono',
        'fecha_nacimiento', 'sexo',
        'niss', 'grupo_cotizacion_tgss',
        'nivel_estudios', 'categoria_profesional',
        'cargo', 'empresa_razon_social', 'empresa_cif',
        'curso_pdf', 'horas_pdf',
    ];

    public function parsear(string $filePath): array
    {
        try {
            $parser = new Parser();
            $pdf    = $parser->parseFile($filePath);
        } catch (Throwable $e) {
            return $this->resultadoIlegible(['PDF protegido, dañado o ilegible: ' . $e->getMessage()]);
        }

        $pages = $pdf->getPages();
        $tipo  = $this->detectarTipo($pdf, $pages);

        // Página del alumno: Ficha → p.1, Encomienda → p.2. Buscamos también
        // por contenido de la página por si el orden no fuera el esperado.
        $paginaAlumno = $this->elegirPaginaAlumno($pages, $tipo);

        $textosXObject = $this->extraerTextosXObject($pdf);
        $textoPaginas  = $this->extraerTextoPaginas($pages);

        $avisos = [];
        $inferidos = [];

        // ESTRATEGIA HÍBRIDA (3 fuentes en orden de fiabilidad):
        //   1. Widget V de la página del alumno (más fiable, evita contaminación
        //      con la página 1 del admin en Encomienda).
        //   2. XObject/Form pool filtrado a la página del alumno (para campos
        //      sin V, típicamente dropdowns o PDFs donde V quedó vacío).
        //   3. Texto plano (fallback para PDFs sin AcroForm).

        $datosWidgets = $paginaAlumno ? $this->extraerCamposDesdeWidgets($paginaAlumno, $avisos) : [];

        $poolAcroForm = [];
        if (!empty($textosXObject)) {
            $poolAcroForm = $textosXObject;
            // Filtrar contaminación admin (página 1 de Encomienda)
            if ($tipo === 'encomienda') {
                $poolAcroForm = $this->filtrarContaminacionPaginaAdmin($poolAcroForm, $pages);
            }
            // Filtrar a XObjects de la página del alumno usando los widget V
            // que sí conocemos como ancla numérica. Cuando no podemos determinar
            // la página, aplicamos el cluster heurístico tradicional.
            $poolAcroForm = $this->filtrarPoolALaPaginaDelAlumno($poolAcroForm, $datosWidgets);

            // Si el usuario editó el PDF varias veces en un viewer, el widget V
            // se queda con el primer valor pero los XObjects de apariencia
            // acumulan las ediciones con IDs altos. Para campos de texto libre
            // (nombre, apellidos, cargo, empresa, curso) buscamos en el pool
            // entradas POSTERIORES al ancla del widget V que compartan al menos
            // una palabra, y usamos la más reciente.
            $datosWidgets = $this->refrescarConEdicionesDelPool($datosWidgets, $poolAcroForm);
        }

        // Ahora sí dividimos apellidos compuestos en apellido1/apellido2.
        $datosWidgets = $this->dividirApellidosFinales($datosWidgets);

        $hayDatosLegibles = !empty($datosWidgets) || !empty($poolAcroForm);
        $textoCombinado = trim($textoPaginas . "\n" . implode("\n", $poolAcroForm) . "\n" . implode("\n", $datosWidgets));

        // Tier 3: si los tiers AcroForm + texto no consiguieron datos, intentamos
        // OCR local con Tesseract (PDF escaneado tipo Ejemplo 3). Es fail-soft:
        // si OCR no está disponible o no devuelve suficiente texto, caemos al
        // ilegible original.
        if (!$hayDatosLegibles && !$this->esLegible($textoCombinado, [])) {
            return $this->intentarOcrOIlegible($filePath, $tipo);
        }

        // Construcción final: widgets son la fuente preferente, pool/texto los completan.
        if (!empty($datosWidgets) || !empty($poolAcroForm)) {
            $datos = $this->fusionarFuentes($datosWidgets, $poolAcroForm, $textoPaginas, $inferidos, $avisos);
        } else {
            $datos = $this->extraerCamposTextoPlano($textoPaginas, $inferidos, $avisos);
        }

        $datos = $this->normalizarCapitalizacion($datos);
        $faltantes = $this->detectarFaltantes($datos);

        return [
            'exito'     => true,
            'tipo'      => $tipo === 'desconocido' ? 'ficha' : $tipo,
            'origen'    => 'acroform',
            'datos'     => $datos,
            'inferidos' => array_values(array_unique($inferidos)),
            'faltantes' => $faltantes,
            'avisos'    => $avisos,
        ];
    }

    /**
     * Tier 3 del pipeline: invoca PdfOcrExtractor (Tesseract local) y
     * reaprovecha la lógica de `extraerCamposTextoPlano()` sobre el texto OCR.
     * Si OCR no está disponible o devuelve poco texto, cae a ilegible.
     */
    private function intentarOcrOIlegible(string $filePath, string $tipo): array
    {
        if (!config('pdf_ocr.enabled', false)) {
            return $this->resultadoIlegible();
        }

        try {
            $ocrText = (new PdfOcrExtractor())->extraerTexto($filePath, $tipo);
        } catch (Throwable $e) {
            report($e);
            return $this->resultadoIlegible([
                'OCR falló: ' . $e->getMessage(),
            ]);
        }

        $minChars = (int) config('pdf_ocr.min_chars', 200);
        if (mb_strlen(trim($ocrText)) < $minChars) {
            return $this->resultadoIlegible();
        }

        $avisos = [];
        $inferidos = [];
        $datos = $this->extraerCamposOcr($ocrText, $inferidos, $avisos);
        $datos = $this->normalizarCapitalizacion($datos);

        // Sanity check: NIF y email son los dos campos con patrones más
        // distintivos. Si OCR no logra extraer ninguno de los dos, el texto
        // OCR es probablemente basura (escaneo de mala calidad o manuscrito).
        // Cae a ilegible para que el admin rellene a mano sin datos falsos.
        if (empty($datos['nif']) && empty($datos['email'])) {
            return $this->resultadoIlegible([
                'OCR no logró identificar datos clave (NIF/email). Posible manuscrito o escaneo de baja calidad — completa los datos manualmente.',
            ]);
        }

        $faltantes = $this->detectarFaltantes($datos);

        // Aviso al admin: revisar campos sensibles a errores OCR.
        $avisos[] = 'Datos extraídos vía OCR. Revisa NIF, NISS, fechas y email — el reconocimiento puede tener errores menores.';

        return [
            'exito'     => true,
            'tipo'      => $tipo === 'desconocido' ? 'ficha' : $tipo,
            'origen'    => 'ocr',
            'datos'     => $datos,
            'inferidos' => array_values(array_unique($inferidos)),
            'faltantes' => $faltantes,
            'avisos'    => $avisos,
        ];
    }

    /**
     * Convierte a Title Case (primera letra mayúscula por palabra, resto en
     * minúscula) los campos PERSONALES — nombre, apellidos y cargo — para que
     * un PDF que llega TODO EN MAYÚSCULAS se muestre como "Greicy Lisbeth
     * Barreto Colmenares" en la UI. Empresa y curso se dejan tal cual porque
     * suelen contener siglas (S.L., NCS) o nombres de marca (ChatGPT) que
     * romper con title case sería contraproducente.
     */
    private function normalizarCapitalizacion(array $datos): array
    {
        foreach (['nombre', 'apellido1', 'apellido2', 'cargo'] as $campo) {
            if (!empty($datos[$campo]) && is_string($datos[$campo])) {
                $datos[$campo] = $this->aTitleCase($datos[$campo]);
            }
        }
        return $datos;
    }

    /**
     * Title Case unicode-safe: primera letra de cada palabra en mayúscula,
     * resto en minúscula. Maneja separadores espacio, guion y apóstrofo.
     */
    private function aTitleCase(string $texto): string
    {
        $texto = trim($texto);
        if ($texto === '') return '';

        $lower = mb_strtolower($texto, 'UTF-8');
        return preg_replace_callback(
            '/(?<=^|[\s\-\'\.])(\p{L})/u',
            fn ($m) => mb_strtoupper($m[1], 'UTF-8'),
            $lower
        );
    }

    /**
     * Elige la página que contiene los datos del alumno.
     * - Ficha: única página.
     * - Encomienda: página 2.
     * - Si no podemos decidir, devolvemos la página con label "Datos del alumno".
     */
    private function elegirPaginaAlumno(array $pages, string $tipo): ?\Smalot\PdfParser\Page
    {
        if (empty($pages)) {
            return null;
        }
        // Buscar por contenido primero
        foreach ($pages as $page) {
            try {
                if (stripos($page->getText(), 'Datos del alumno') !== false) {
                    return $page;
                }
            } catch (Throwable) {}
        }
        if ($tipo === 'encomienda' && isset($pages[1])) {
            return $pages[1];
        }
        return $pages[0] ?? null;
    }

    /**
     * Extrae los valores directamente del campo V de los widgets de una página.
     * Mapea nombres de campo (T) — incluidos los mojibake de smalot — a las
     * claves de nuestro array de datos.
     *
     * @return array<string, mixed>
     */
    private function extraerCamposDesdeWidgets(\Smalot\PdfParser\Page $page, array &$avisos): array
    {
        $datos = [];
        $header = $page->getHeader();
        if (!$header->has('Annots')) {
            return $datos;
        }
        $annots = $header->get('Annots');
        if (!$annots instanceof \Smalot\PdfParser\Element\ElementArray) {
            return $datos;
        }

        $mapeoLabels = [
            // T normalizado (lowercase, sin acentos/símbolos) => clave del array datos
            'nombre'                                 => 'nombre',
            'apellidos'                              => 'apellidos_completo',
            'curso'                                  => 'curso_pdf',
            'horas'                                  => 'horas_pdf',
            'tel'                                    => 'telefono',
            'telefono'                               => 'telefono',
            'telfono'                                => 'telefono',  // mojibake: "Teléfono" → é dropped
            'email'                                  => 'email',
            'fecha de nacimiento'                    => 'fecha_nacimiento',
            'nifnie'                                 => 'nif',
            'nif nie'                                => 'nif',
            'nif'                                    => 'nif',
            'n de la seguridad social 12 digitos'    => 'niss',
            'n de la seguridad social 12 dgitos'     => 'niss',  // mojibake: "dígitos" → í dropped
            'seguridad social'                       => 'niss',
            'niss'                                   => 'niss',
            'profesion y titulacion'                 => 'cargo',
            'profesin y titulacin'                   => 'cargo',  // mojibake: "Profesión y titulación" → ó dropped
            'cargo y titulacion'                     => 'cargo',
            'cargo y titulacin'                      => 'cargo',  // mojibake
            'cargo'                                  => 'cargo',
            'empresa'                                => 'empresa_razon_social',
            'cif'                                    => 'empresa_cif',
        ];

        foreach ($annots->getContent() as $annot) {
            if (!is_object($annot)) continue;
            try {
                $details = $annot->getHeader()->getDetails();
            } catch (Throwable) {
                continue;
            }
            $subtype = $details['Subtype'] ?? '';
            if ($subtype !== 'Widget') continue;

            $t = $details['T'] ?? '';
            $v = $details['V'] ?? null;
            if ($v === null || $v === '' || is_array($v)) continue;

            $keyT = $this->normalizarTextoMapeo((string) $t);
            if (!isset($mapeoLabels[$keyT])) continue;

            $clave = $mapeoLabels[$keyT];
            $datos[$clave] = trim((string) $v);
        }

        // Sexo: detectamos por la posición de las casillas (Rect). En el
        // formulario hay dos checkboxes a la misma altura Y, con la H a la
        // izquierda y la M a la derecha (etiqueta "Sexo: H ☐ M ☐"). Miramos
        // el Appearance State (AS) de cada uno para ver cuál está marcado.
        $sexo = $this->extraerSexoDeCheckboxes($page);
        if ($sexo !== null) {
            $datos['sexo'] = $sexo;
        }

        // NOTA: NO dividimos apellidos aquí. Se hace después en parsear() tras
        // aplicar refrescarConEdicionesDelPool, que podría sustituir el valor
        // de apellidos_completo por una edición posterior del usuario en el PDF.

        // Normalizaciones de salida
        if (isset($datos['nif'])) {
            $datos['nif'] = $this->normalizarNif($datos['nif']);
        }
        if (isset($datos['email'])) {
            $datos['email'] = $this->normalizarEmail($datos['email'], $avisos);
        }
        if (isset($datos['fecha_nacimiento'])) {
            $datos['fecha_nacimiento'] = $this->normalizarFecha($datos['fecha_nacimiento']);
        }
        if (isset($datos['telefono'])) {
            $datos['telefono'] = $this->normalizarTelefono($datos['telefono']);
        }
        if (isset($datos['niss'])) {
            $datos['niss'] = $this->normalizarNiss($datos['niss']);
        }
        if (isset($datos['empresa_cif'])) {
            $datos['empresa_cif'] = $this->normalizarCif($datos['empresa_cif']);
        }
        if (isset($datos['horas_pdf']) && preg_match('/(\d+)/', (string) $datos['horas_pdf'], $m)) {
            $datos['horas_pdf'] = (int) $m[1];
        }

        return array_filter($datos, fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Detecta el sexo (H/M) mirando los checkboxes de la página del alumno.
     * Estrategia: recorre todos los widgets que tienen un Rect y un campo AS
     * (Appearance State), los agrupa por Y aproximadamente igual (±10pt) y
     * busca grupos de exactamente 2 checkboxes — esa es la pareja H/M de la
     * fila "Sexo:" del formulario. La casilla con X menor es H, la mayor es M;
     * la que tenga AS distinto de "Off" es la marcada.
     */
    private function extraerSexoDeCheckboxes(\Smalot\PdfParser\Page $page): ?string
    {
        $header = $page->getHeader();
        if (!$header->has('Annots')) {
            return null;
        }
        $annots = $header->get('Annots');
        if (!$annots instanceof \Smalot\PdfParser\Element\ElementArray) {
            return null;
        }

        $checkboxes = [];
        foreach ($annots->getContent() as $annot) {
            if (!is_object($annot)) continue;
            try {
                $details = $annot->getHeader()->getDetails();
            } catch (Throwable) {
                continue;
            }
            if (($details['Subtype'] ?? '') !== 'Widget') continue;

            $rect = $details['Rect'] ?? null;
            if (!is_array($rect) || count($rect) < 4) continue;

            // Es checkbox si tiene FT=Btn o si tiene un AS (estado de apariencia).
            $ft = $details['FT'] ?? null;
            $as = $details['AS'] ?? null;
            $esCheckbox = ($ft === 'Btn') || ($as !== null && $as !== '');
            if (!$esCheckbox) continue;

            $checkboxes[] = [
                'x' => (float) $rect[0],
                'y' => (float) $rect[1],
                'as' => (string) $as,
            ];
        }

        if (count($checkboxes) < 2) {
            return null;
        }

        // Buscar grupos de 2 checkboxes a la misma altura (ΔY ≤ 10pt)
        foreach ($checkboxes as $i => $a) {
            foreach ($checkboxes as $j => $b) {
                if ($i >= $j) continue;
                if (abs($a['y'] - $b['y']) > 10) continue;

                // Encontramos un par a la misma altura: H = el de menor X, M = mayor X.
                [$h, $m] = $a['x'] <= $b['x'] ? [$a, $b] : [$b, $a];

                $hMarcado = $this->checkboxMarcado($h['as']);
                $mMarcado = $this->checkboxMarcado($m['as']);
                if ($hMarcado && !$mMarcado) return 'H';
                if ($mMarcado && !$hMarcado) return 'M';
                // Si ambos marcados o ninguno → ambiguo, devolvemos null
                return null;
            }
        }

        return null;
    }

    /**
     * Un checkbox está marcado cuando su Appearance State no es "Off" ni vacío.
     * Los valores marcados típicos son "Sí", "Yes", "On", o el nombre del estado.
     */
    private function checkboxMarcado(string $as): bool
    {
        $as = trim($as);
        if ($as === '' || $as === 'Off') return false;
        return true;
    }

    /**
     * Para cada campo de texto libre (nombre, apellidos, cargo, empresa, curso),
     * comprueba si el pool de XObjects contiene una edición POSTERIOR al
     * widget V que comparta al menos una palabra significativa. Si la hay,
     * sustituye el valor del widget V por la más reciente. Esto resuelve el
     * caso de PDFs editados varias veces en un viewer donde widget V queda
     * obsoleto pero los appearance streams reflejan el último valor visible.
     */
    private function refrescarConEdicionesDelPool(array $datosWidgets, array $poolAcroForm): array
    {
        if (empty($datosWidgets) || empty($poolAcroForm)) {
            return $datosWidgets;
        }

        $camposScored = ['nombre', 'apellidos_completo', 'cargo', 'curso_pdf', 'empresa_razon_social'];

        foreach ($camposScored as $clave) {
            if (empty($datosWidgets[$clave]) || !is_string($datosWidgets[$clave])) {
                continue;
            }
            $valorWidget = trim($datosWidgets[$clave]);
            if ($valorWidget === '') continue;

            $tokensWidget = $this->tokensSignificativos($valorWidget);
            if (empty($tokensWidget)) continue;

            // ID ancla: posición del valor del widget V en el pool. Si no
            // está, asumimos ancla = 0 (cualquier match cuenta como edición).
            $idAncla = 0;
            foreach ($poolAcroForm as $id => $contenido) {
                if (trim($contenido) === $valorWidget) {
                    if (preg_match('/^(\d+)_/', $id, $m)) {
                        $idAncla = (int) $m[1];
                        break;
                    }
                }
            }

            // Buscar candidatos: pool entries con ID > ancla que compartan
            // al menos una palabra significativa con el valor del widget V.
            // Excluimos valores que claramente pertenecen a OTROS campos
            // (email, NIF, NISS, fecha, teléfono, CIF) — ej. el email
            // "lola.angelica1235456@gmail.com" no debe sustituir al nombre
            // "Lola Angelica" aunque comparta el token "lola".
            $candidatos = [];
            foreach ($poolAcroForm as $id => $contenido) {
                if (!preg_match('/^(\d+)_/', $id, $m)) continue;
                $idNum = (int) $m[1];
                if ($idNum <= $idAncla) continue;

                $contenidoTrim = trim($contenido);
                if ($contenidoTrim === '' || $contenidoTrim === $valorWidget) continue;
                if ($this->esValorDeOtroCampo($contenidoTrim)) continue;

                $tokensCandidato = $this->tokensSignificativos($contenidoTrim);
                if (empty($tokensCandidato)) continue;

                $overlap = array_intersect($tokensCandidato, $tokensWidget);
                if (!empty($overlap)) {
                    $candidatos[$idNum] = $contenidoTrim;
                }
            }

            if (!empty($candidatos)) {
                ksort($candidatos);
                $maxId = max(array_keys($candidatos));
                $datosWidgets[$clave] = $candidatos[$maxId];
            }
        }

        return $datosWidgets;
    }

    /**
     * Determina si un valor del pool pertenece claramente a OTRO campo
     * (email, NIF, NISS, fecha, teléfono, CIF, solo dígitos). Usado por
     * refrescarConEdicionesDelPool para no confundir, por ejemplo, un email
     * con un edit del nombre cuando comparten un token como "lola".
     */
    private function esValorDeOtroCampo(string $valor): bool
    {
        $v = trim($valor);
        if ($v === '') return false;

        // Email
        if (preg_match('/[\w\.\-]+[\*@][\w\.\-]+\.\w{2,}/u', $v)) return true;
        // NIF / NIE
        if (preg_match('/^\s*[XYZ]?\d{7,8}[A-Za-z]\s*$/u', $v)) return true;
        // Fecha dd/mm/yyyy / dd-mm-yyyy / dd.mm.yyyy
        if (preg_match('/^\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}$/', $v)) return true;
        // CIF empresa (B/A/.. + 8 dígitos)
        if (preg_match('/^[A-HJNPQRSUVW]\d{8}$/iu', $v)) return true;
        // Solo dígitos (teléfono, NISS, NIF sin letra, importes, etc.)
        if (preg_match('/^\d+$/', preg_replace('/[\s\-\.\/]+/', '', $v))) return true;

        return false;
    }

    /**
     * Tokeniza un texto y devuelve solo palabras significativas (lowercase,
     * mínimo 3 caracteres, sin acentos). Usado para comparar similitud
     * entre widget V y candidatos del pool.
     *
     * @return array<int, string>
     */
    private function tokensSignificativos(string $texto): array
    {
        $t = $this->normalizarTextoMapeo($texto);
        $tokens = preg_split('/\s+/u', $t) ?: [];
        $tokens = array_filter($tokens, fn ($w) => mb_strlen($w) >= 3);
        return array_values(array_unique($tokens));
    }

    /**
     * Después del refresco con ediciones del pool, divide apellidos_completo
     * en apellido1/apellido2.
     */
    private function dividirApellidosFinales(array $datos): array
    {
        if (!empty($datos['apellidos_completo'])) {
            [$ap1, $ap2] = $this->separarApellidosPdf($datos['apellidos_completo']);
            $datos['apellido1'] = $ap1;
            $datos['apellido2'] = $ap2;
            unset($datos['apellidos_completo']);
        }
        return $datos;
    }

    /**
     * Si tenemos valores conocidos de la página del alumno (vía widget V),
     * encontramos el rango de IDs numéricos donde están sus XObjects asociados
     * y descartamos los XObjects que estén fuera de ese rango. Si no hay valores
     * ancla, caemos al cluster heurístico anterior.
     *
     * @param array<string,string> $pool
     * @param array<string,mixed>  $datosWidgets
     * @return array<string,string>
     */
    private function filtrarPoolALaPaginaDelAlumno(array $pool, array $datosWidgets): array
    {
        if (empty($pool)) {
            return [];
        }
        if (empty($datosWidgets)) {
            // Sin anclas — usar la heurística de cluster.
            return $this->mantenerSoloUltimoCluster($pool);
        }

        $valoresAncla = [];
        foreach ($datosWidgets as $clave => $valor) {
            if (in_array($clave, ['nombre', 'empresa_razon_social', 'cargo', 'curso_pdf'], true)
                && is_string($valor) && $valor !== '') {
                $valoresAncla[] = $valor;
            }
        }
        if (empty($valoresAncla)) {
            return $this->mantenerSoloUltimoCluster($pool);
        }

        // Buscar IDs cuyo content coincide con cualquier valor ancla
        $idsAncla = [];
        foreach ($pool as $id => $content) {
            $contentTrim = trim($content);
            foreach ($valoresAncla as $ancla) {
                if ($contentTrim === $ancla) {
                    if (preg_match('/^(\d+)_/', $id, $m)) {
                        $idsAncla[] = (int) $m[1];
                    }
                    break;
                }
            }
        }
        if (empty($idsAncla)) {
            return $this->mantenerSoloUltimoCluster($pool);
        }

        $minAncla = min($idsAncla);
        $maxAncla = max($idsAncla);
        // Conservamos: (a) el cluster del alumno con un margen para incluir
        // dropdowns/labels cercanos, y (b) cualquier ID por encima del cluster,
        // porque cuando el usuario edita un campo del PDF después de la primera
        // grabación, el viewer crea appearance XObjects con IDs progresivamente
        // más altos. Como el filtro admin ya eliminó la página 1, cualquier
        // entrada con ID > maxAncla en el pool restante es una edición del
        // alumno y debe conservarse para que refrescarConEdicionesDelPool la vea.
        $margen = 50;
        $resultado = [];
        foreach ($pool as $id => $content) {
            if (!preg_match('/^(\d+)_/', $id, $m)) continue;
            $n = (int) $m[1];
            if ($n >= ($minAncla - $margen)) {
                $resultado[$id] = $content;
            }
        }
        return $resultado;
    }

    /**
     * Fusiona valores de widget V (preferentes) con los del pool AcroForm
     * (para los campos que aún están vacíos, típicamente dropdowns).
     *
     * @param array<string, mixed>  $datosWidgets
     * @param array<string, string> $poolAcroForm
     * @param array<int, string>    $inferidos
     * @param array<int, string>    $avisos
     * @return array<string, mixed>
     */
    private function fusionarFuentes(array $datosWidgets, array $poolAcroForm, string $textoPaginas, array &$inferidos, array &$avisos): array
    {
        $base = array_fill_keys(self::TODOS_LOS_CAMPOS, null);

        // 1. Aplicar valores directos de widgets V
        foreach ($datosWidgets as $clave => $valor) {
            if (array_key_exists($clave, $base)) {
                $base[$clave] = $valor;
            }
        }

        // 2. Pool de XObject = "lo último que se ve en pantalla". Para campos
        // identificables por patrón (email, NIF, fecha, etc.), el pool SIEMPRE
        // gana sobre widget V — porque V queda obsoleto cuando el usuario edita
        // el PDF en un viewer que solo actualiza la apariencia. Para campos sin
        // patrón claro (nombre/apellidos/cargo/curso/empresa), widget V gana y
        // el pool solo rellena huecos.
        if (!empty($poolAcroForm)) {
            $extras = $this->extraerCamposAcroForm($poolAcroForm, $textoPaginas, $inferidos, $avisos);

            $camposPatronablesPoolGana = [
                'email', 'nif', 'niss', 'fecha_nacimiento', 'telefono',
                'empresa_cif', 'horas_pdf',
                'grupo_cotizacion_tgss', 'nivel_estudios', 'categoria_profesional',
            ];

            // Si widget V proporcionó el campo de apellidos (split en
            // apellido1/apellido2 por dividirApellidosFinales), bloqueamos al
            // pool para que NO sobreescriba apellido1 ni rellene apellido2.
            // El scoring greedy del pool puede asignar erróneamente "Demo 1"
            // a la posición de apellidos y meter el "1" en apellido2 cuando
            // widget V dijo claramente "apellidos = Marcos" (solo uno).
            $widgetVProveyoApellido = isset($datosWidgets['apellido1']);

            foreach ($extras as $clave => $valor) {
                if ($valor === null || $valor === '') continue;
                if ($widgetVProveyoApellido && in_array($clave, ['apellido1', 'apellido2'], true)) {
                    continue;
                }
                $poolGana = in_array($clave, $camposPatronablesPoolGana, true);
                if ($poolGana || empty($base[$clave])) {
                    $base[$clave] = $valor;
                }
            }
        }

        return $base;
    }

    // ──────────────────────────────────────────────────────────────
    //  Detección de tipo y legibilidad
    // ──────────────────────────────────────────────────────────────

    private function detectarTipo($pdf, array $pages): string
    {
        $textoCompleto = '';
        foreach ($pages as $page) {
            $textoCompleto .= ' ' . $page->getText();
        }
        // Para AcroForm, el texto de plantilla menciona "CONTRATO DE ENCOMIENDA" en p1
        // y "FICHA DE INSCRIPCIÓN" en cabecera. Si la plantilla no está visible (raro),
        // usamos número de páginas como fallback.

        $hayEncomienda = stripos($textoCompleto, 'CONTRATO DE ENCOMIENDA') !== false
            || stripos($textoCompleto, 'ENCOMIENDA DE ORGANIZACI') !== false;
        $hayFicha = stripos($textoCompleto, 'FICHA DE INSCRIPCI') !== false;

        if ($hayEncomienda) {
            return 'encomienda';
        }
        if ($hayFicha) {
            return 'ficha';
        }
        $numPaginas = count($pages);
        if ($numPaginas >= 2) {
            return 'encomienda';
        }
        if ($numPaginas === 1) {
            return 'ficha';
        }
        return 'desconocido';
    }

    private function esLegible(string $textoCombinado, array $textosXObject): bool
    {
        // Si hay valores extraídos de XObject (AcroForm relleno) → legible
        if (!empty($textosXObject)) {
            return true;
        }
        // Si no hay AcroForm, requerimos texto significativo + algún campo detectable
        if (mb_strlen(trim($textoCombinado)) < 200) {
            return false;
        }
        $tieneNif    = (bool) preg_match('/[XYZ]?\d{7,8}[A-Za-z]/u', $textoCombinado);
        $tieneEmail  = (bool) preg_match('/[\w\.\-]+@[\w\.\-]+\.\w{2,}/u', $textoCombinado);
        $tieneNombre = stripos($textoCombinado, 'Nombre') !== false;

        return $tieneNif || $tieneEmail || $tieneNombre;
    }

    // ──────────────────────────────────────────────────────────────
    //  Extracción de textos crudos
    // ──────────────────────────────────────────────────────────────

    private function extraerTextoPaginas(array $pages): string
    {
        $texto = '';
        foreach ($pages as $page) {
            $texto .= "\n" . $page->getText();
        }
        return $this->normalizarSaltos($texto);
    }

    /**
     * Extrae el texto de todos los objetos XObject/Form del PDF.
     * Son los appearance streams donde quedan los valores rellenados
     * de un AcroForm cuando el PDF se guarda con apariencia.
     *
     * @return array<string, string>  [object_id => texto extraído, no vacío]
     */
    private function extraerTextosXObject($pdf): array
    {
        $resultado = [];
        try {
            $forms = $pdf->getObjectsByType('XObject', 'Form');
        } catch (Throwable) {
            return [];
        }

        foreach ($forms as $id => $form) {
            try {
                $texto = trim((string) $form->getText());
            } catch (Throwable) {
                continue;
            }
            // Filtrar valores genéricos sin información (un solo dígito, vacíos, marcas check)
            if ($texto === '' || $this->esRuidoXObject($texto)) {
                continue;
            }
            $resultado[(string) $id] = $texto;
        }
        return $resultado;
    }

    /**
     * Para Encomienda: elimina del pool de XObjects una ocurrencia por cada V
     * de widget en página 1 (datos del administrador firmante). Esto evita
     * confundir el NIF, email, teléfono y nombre del firmante con los del
     * alumno. Preserva duplicados (ej. razón social aparece en p1 y p2).
     *
     * @param array<string,string> $pool
     * @param array<int,\Smalot\PdfParser\Page> $pages
     * @return array<string,string>
     */
    private function filtrarContaminacionPaginaAdmin(array $pool, array $pages): array
    {
        if (!isset($pages[0])) {
            return $pool;
        }
        try {
            $valoresAdmin = $this->obtenerWidgetVDePagina($pages[0]);
        } catch (Throwable) {
            return $pool;
        }

        // Identificar el cluster admin como rango de IDs: buscamos en el pool
        // los IDs cuyo content coincide ÚNICAMENTE con un valor de widget V
        // de la página admin (estos son anclas seguras: nombre del firmante,
        // NIF admin, dirección, email admin, etc.). El rango min..max de esas
        // IDs ± margen define el cluster admin. Eliminamos todo dentro de él.
        //
        // Importante: usamos solo anclas ÚNICAS porque valores compartidos
        // entre admin y alumno (ej. razón social común) no pueden discriminar.
        $idsAdmin = [];
        foreach ($valoresAdmin as $valor) {
            $valor = trim($valor);
            if ($valor === '') continue;

            $idsCoincidentes = [];
            foreach ($pool as $id => $contenido) {
                if (trim($contenido) === $valor) {
                    if (preg_match('/^(\d+)_/', $id, $m)) {
                        $idsCoincidentes[] = (int) $m[1];
                    }
                }
            }
            if (count($idsCoincidentes) === 1) {
                $idsAdmin[] = $idsCoincidentes[0];
            }
        }

        if (empty($idsAdmin)) {
            // Sin anclas únicas → no podemos delimitar el cluster admin
            // con seguridad. Caemos al método previo (remover una ocurrencia
            // por valor, partiendo del ID más bajo).
            uksort($pool, function ($a, $b) {
                preg_match('/^(\d+)_/', $a, $ma);
                preg_match('/^(\d+)_/', $b, $mb);
                return ((int)($ma[1] ?? 0)) <=> ((int)($mb[1] ?? 0));
            });
            foreach ($valoresAdmin as $valor) {
                $valor = trim($valor);
                if ($valor === '') continue;
                foreach ($pool as $id => $contenido) {
                    if (trim($contenido) === $valor) {
                        unset($pool[$id]);
                        break;
                    }
                }
            }
            return $pool;
        }

        $margen = 50;
        $minAdmin = min($idsAdmin) - $margen;
        $maxAdmin = max($idsAdmin) + $margen;

        $resultado = [];
        foreach ($pool as $id => $contenido) {
            if (!preg_match('/^(\d+)_/', $id, $m)) {
                $resultado[$id] = $contenido;
                continue;
            }
            $n = (int) $m[1];
            if ($n < $minAdmin || $n > $maxAdmin) {
                $resultado[$id] = $contenido;
            }
        }
        return $resultado;
    }

    /**
     * Conserva solo el cluster de XObject IDs más altos (numeric). Esto descarta
     * plantillas de dropdown que se crearon al diseñar el PDF (IDs bajos) y deja
     * solo los appearance streams del último guardado (IDs altos).
     *
     * @param array<string,string> $pool
     * @return array<string,string>
     */
    private function mantenerSoloUltimoCluster(array $pool): array
    {
        if (count($pool) <= 1) {
            return $pool;
        }

        // Extraer parte numérica del ID (formato "NNN_M")
        $idsNumericos = [];
        foreach ($pool as $id => $_) {
            if (preg_match('/^(\d+)_/', $id, $m)) {
                $idsNumericos[$id] = (int) $m[1];
            }
        }
        if (empty($idsNumericos)) {
            return $pool;
        }

        // Ordenar por número
        asort($idsNumericos);
        $ordenados = array_values($idsNumericos);

        // Buscar el último gap grande (>= 100) y separar
        $clusterInicio = $ordenados[0];
        for ($i = count($ordenados) - 1; $i > 0; $i--) {
            if ($ordenados[$i] - $ordenados[$i - 1] >= 100) {
                $clusterInicio = $ordenados[$i];
                break;
            }
        }

        $resultado = [];
        foreach ($idsNumericos as $id => $num) {
            if ($num >= $clusterInicio) {
                $resultado[$id] = $pool[$id];
            }
        }
        return $resultado;
    }

    /**
     * Devuelve la lista de valores V de los widgets anotados en la página dada.
     *
     * @return array<int,string>
     */
    private function obtenerWidgetVDePagina(\Smalot\PdfParser\Page $page): array
    {
        $valores = [];
        $header = $page->getHeader();
        if (!$header->has('Annots')) {
            return [];
        }
        $annots = $header->get('Annots');
        if (!$annots instanceof \Smalot\PdfParser\Element\ElementArray) {
            return [];
        }
        $lista = $annots->getContent();
        foreach ($lista as $annot) {
            if (!is_object($annot)) continue;
            $details = $annot->getHeader()->getDetails();
            $subtype = $details['Subtype'] ?? '';
            if ($subtype !== 'Widget') continue;
            $v = $details['V'] ?? null;
            if ($v === null || $v === '' || is_array($v)) continue;
            $valores[] = (string) $v;
        }
        return $valores;
    }

    private function esRuidoXObject(string $texto): bool
    {
        $t = trim($texto);
        // Valores típicos de checkboxes / decoración
        if (in_array($t, ['4', '8', 'X', 'x', '✓', '✔', 'Off', 'Yes', 'No'], true)) {
            return true;
        }
        // Solo un dígito
        if (preg_match('/^\d$/u', $t)) {
            return true;
        }
        return false;
    }

    // ──────────────────────────────────────────────────────────────
    //  Extracción por AcroForm: pattern matching sobre los valores
    // ──────────────────────────────────────────────────────────────

    /**
     * @param array<string,string> $valores      values pool
     * @param string               $textoPaginas para fallback de label-based
     * @param array<int,string>    $inferidos
     * @param array<int,string>    $avisos
     */
    private function extraerCamposAcroForm(array $valores, string $textoPaginas, array &$inferidos, array &$avisos): array
    {
        $datos = array_fill_keys(self::TODOS_LOS_CAMPOS, null);

        // Ordenar por ID numérico DESCENDENTE para la fase de pattern matching:
        // cuando el usuario edita un campo varias veces, cada edición crea un
        // appearance XObject nuevo con ID más alto. Iterando desde el más
        // reciente garantizamos coger el ÚLTIMO valor escrito (el que se ve
        // en pantalla). Guardamos la correspondencia posición→ID original
        // para reordenar después los restantes ASC (necesario para el
        // scoring de nombre/apellidos/curso, donde el orden ascendente es
        // estable).
        uksort($valores, function ($a, $b) {
            preg_match('/^(\d+)_/', $a, $ma);
            preg_match('/^(\d+)_/', $b, $mb);
            return ((int)($mb[1] ?? 0)) <=> ((int)($ma[1] ?? 0));
        });

        $idsOriginales = array_keys($valores);  // posición → ID original (DESC)
        $pool  = array_values($valores);  // copia mutable, posiciones 0..N-1
        $usados = [];

        $tomar = function (callable $matcher) use (&$pool, &$usados): ?string {
            foreach ($pool as $i => $v) {
                if (isset($usados[$i])) continue;
                if ($matcher($v)) {
                    $usados[$i] = true;
                    return $v;
                }
            }
            return null;
        };

        // 1. NIF/NIE (formato: 8 dígitos + letra, o X/Y/Z + 7 dígitos + letra)
        $nifRaw = $tomar(fn($v) => preg_match('/^\s*[XYZ]?\d{7,8}[A-Za-z]\s*$/u', $v));
        $datos['nif'] = $this->normalizarNif($nifRaw);

        // 2. Email
        $emailRaw = $tomar(fn($v) => preg_match('/[\w\.\-]+[\*@][\w\.\-]+\.\w{2,}/u', $v)
            && !preg_match('/^[A-Z][a-záéíóúñ]+\s+[A-Z][a-záéíóúñ]+$/u', $v)
        );
        $datos['email'] = $this->normalizarEmail($emailRaw, $avisos);

        // 3. NISS (12 dígitos, posiblemente con espacios)
        $nissRaw = $tomar(function ($v) {
            $solo = preg_replace('/\D/', '', $v);
            return strlen($solo) === 12;
        });
        $datos['niss'] = $this->normalizarNiss($nissRaw);

        // 4. Fecha nacimiento (formato dd/mm/yyyy o dd-mm-yyyy)
        $fechaRaw = $tomar(fn($v) => preg_match('/^\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}$/', trim($v)));
        $datos['fecha_nacimiento'] = $this->normalizarFecha($fechaRaw);

        // 5. CIF empresa (B/A/C/D/E/F/G/H/J/N/P/Q/R/S/U/V/W + 8 dígitos)
        $cifRaw = $tomar(fn($v) => preg_match('/^[A-HJNPQRSUVW]\d{8}$/iu', trim($v)));
        $datos['empresa_cif'] = $this->normalizarCif($cifRaw);

        // 6. Teléfono (9 dígitos, posiblemente con espacios)
        $telRaw = $tomar(function ($v) {
            $solo = preg_replace('/\D/', '', $v);
            return strlen($solo) === 9
                && !preg_match('/^\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]/', $v); // no fecha
        });
        $datos['telefono'] = $this->normalizarTelefono($telRaw);

        // 7. Grupo cotización TGSS (texto descriptivo)
        $tgssRaw = $tomar(fn($v) => $this->mapearGrupoCotizacionPdf($v) !== null);
        $datos['grupo_cotizacion_tgss'] = $this->mapearGrupoCotizacionPdf($tgssRaw);

        // 8. Nivel estudios (texto descriptivo)
        $estudiosRaw = $tomar(function ($v) {
            [$cod, $_] = $this->mapearNivelEstudiosPdf($v);
            return $cod !== null;
        });
        [$nivelCod, $nivelInferido] = $this->mapearNivelEstudiosPdf($estudiosRaw);
        $datos['nivel_estudios'] = $nivelCod;
        if ($nivelInferido) {
            $inferidos[] = 'nivel_estudios';
        }

        // 9. Categoría profesional (texto descriptivo)
        $catRaw = $tomar(fn($v) => $this->mapearCategoriaProfesionalPdf($v) !== null);
        $datos['categoria_profesional'] = $this->mapearCategoriaProfesionalPdf($catRaw);

        // 10. Horas (1-4 dígitos solos)
        $horasRaw = $tomar(fn($v) => preg_match('/^\s*\d{1,4}\s*$/', $v));
        if ($horasRaw) {
            $datos['horas_pdf'] = (int) trim($horasRaw);
        }

        // 11. Empresa razón social: requiere sufijo de forma jurídica (SL, SA, etc.)
        // para evitar falsos positivos con nombres personales en mayúsculas.
        $empresaRaw = $tomar(function ($v) {
            return preg_match('/\b(S\.?\s?L\.?\s?U?\.?|S\.?\s?A\.?\s?U?\.?|S\.?C\.?P\.?|SCOOP|S\.?\s?COOP|S\.?\s?L\.?\s?N\.?\s?E\.?|CB)\b/iu', $v) === 1;
        });
        $datos['empresa_razon_social'] = $empresaRaw ? trim($empresaRaw) : null;

        // 12. Cargo: texto que matchea keywords de cargo (Responsable, Director, etc.)
        //     o el valor más largo (>20 chars) que no contenga firma digital.
        $candidatosCargo = [];
        foreach ($pool as $i => $v) {
            if (isset($usados[$i])) continue;
            $vt = trim($v);
            if ($vt === '') continue;
            if (preg_match('/Firmado digitalmente|\d{4}\.\d{2}\.\d{2}/iu', $vt)) continue;
            if (preg_match('/^\d+$/', $vt)) continue;
            $candidatosCargo[$i] = $vt;
        }
        $idxCargo = null;
        foreach ($candidatosCargo as $i => $vt) {
            if (preg_match('/\b(responsable|director|directora|jefe|jefa|coordinador|coordinadora|t[eé]cnic[oa]|mando|gerente|operari[oa]|operador|oficial|auxiliar|administrador|ingenier[oa]|comercial|vendedor|asesor|supervisor|programador|analista|contable|secretari[oa])\b/iu', $vt)) {
                $idxCargo = $i;
                break;
            }
        }
        if ($idxCargo === null) {
            $maxLen = 0;
            foreach ($candidatosCargo as $i => $vt) {
                $len = mb_strlen($vt);
                if ($len > 20 && $len > $maxLen) {
                    $maxLen = $len;
                    $idxCargo = $i;
                }
            }
        }
        if ($idxCargo !== null) {
            $datos['cargo'] = trim($pool[$idxCargo]);
            $usados[$idxCargo] = true;
        }

        // 13-15. Asignar nombre / apellidos / curso entre los valores restantes
        //        usando scoring greedy. El scoring resuelve ties tomando el
        //        primer máximo encontrado en orden de iteración; aquí
        //        reordenamos ASC por ID original para que el primer match
        //        sea el del PDF original (no el de una edición posterior),
        //        manteniendo el comportamiento estable para casos sin ediciones.
        $restantes = [];
        foreach ($pool as $i => $v) {
            if (isset($usados[$i])) continue;
            $vt = trim($v);
            if ($vt === '' || mb_strlen($vt) > 100) continue;
            if (preg_match('/Firmado digitalmente|\d{4}\.\d{2}\.\d{2}/iu', $vt)) continue;
            if (preg_match('/[@\/\\\\]/', $vt)) continue;
            $restantes[$i] = $vt;
        }

        // Reordenar restantes por ID original ASC (los iteramos por posición
        // i en pool DESC, así que i pequeño = ID alto). Para scoring necesitamos
        // ASC: iteramos en orden inverso de pool.
        $restantesAsc = [];
        foreach (array_reverse($restantes, true) as $i => $v) {
            $restantesAsc[$i] = $v;
        }
        $restantes = $restantesAsc;

        $asignacion = $this->asignarNombreApellidosCurso($restantes);
        if (isset($asignacion['nombre'])) {
            $datos['nombre'] = $restantes[$asignacion['nombre']];
            $usados[$asignacion['nombre']] = true;
        }
        if (isset($asignacion['apellidos'])) {
            [$ap1, $ap2] = $this->separarApellidosPdf($restantes[$asignacion['apellidos']]);
            $datos['apellido1'] = $ap1;
            $datos['apellido2'] = $ap2;
            $usados[$asignacion['apellidos']] = true;
        }
        if (isset($asignacion['curso'])) {
            $datos['curso_pdf'] = $restantes[$asignacion['curso']];
            $usados[$asignacion['curso']] = true;
        }

        // 16. Sexo — buscamos en texto de páginas el patrón "Sexo: H X" o similar
        $datos['sexo'] = $this->extraerSexoDeTexto($textoPaginas);
        if ($datos['sexo'] === null) {
            // En AcroForm el sexo suele estar en checkbox cuyo XObject es solo "4"
            // (ya filtrado como ruido). Inferimos por heurística posterior si es posible.
        }

        return $datos;
    }

    /**
     * Asigna los valores residuales a roles nombre / apellidos / curso usando
     * scoring greedy. Cada valor recibe un score para cada rol según patrones
     * de capitalización, número de palabras y keywords. Se eligen los tres
     * pares (valor, rol) que maximizan el score total.
     *
     * @param array<int, string> $valores  Pool indexado por key original
     * @return array<string, int>          ['nombre'=>idx, 'apellidos'=>idx, 'curso'=>idx]
     */
    private function asignarNombreApellidosCurso(array $valores): array
    {
        if (empty($valores)) {
            return [];
        }

        $roles = ['nombre', 'apellidos', 'curso'];
        $matriz = [];

        foreach ($valores as $idx => $valor) {
            $palabras   = preg_split('/\s+/', trim($valor)) ?: [];
            $nPalabras  = count(array_filter($palabras, fn($w) => $w !== ''));
            $esTodoCaps = $valor !== mb_strtolower($valor)
                && $valor === mb_strtoupper($valor);
            $esTitulo   = !$esTodoCaps && preg_match('/^[A-ZÁÉÍÓÚÑ]/u', $valor) === 1
                && preg_match('/[a-záéíóúñ]/u', $valor) === 1;
            $esCursoKeyword = preg_match(
                '/\b(curso|claude|chat\s?gpt|gpt|openai|anthropic|code|excel|word|powerpoint|office|access|outlook|sap|n[oó]minas|seguridad\s+social|contrataci[oó]n|laboral|marketing|contabilidad|fiscal|prl|prevenci[oó]n|riesgos|protecci[oó]n|rgpd|atenci[oó]n)\b/iu',
                $valor
            ) === 1;

            $scoreNombre = 0;
            if ($esTodoCaps && $nPalabras >= 2) $scoreNombre += 3;
            elseif ($esTodoCaps && $nPalabras === 1) $scoreNombre += 1;
            if ($esTitulo && $nPalabras === 1) $scoreNombre += 2;
            if ($esTitulo && $nPalabras >= 2) $scoreNombre += 1;
            if (mb_strlen($valor) > 30) $scoreNombre -= 2;

            $scoreApellidos = 0;
            if ($esTitulo && $nPalabras >= 2) $scoreApellidos += 3;
            if ($esTodoCaps && $nPalabras === 1) $scoreApellidos += 2;
            if ($esTodoCaps && $nPalabras >= 2) $scoreApellidos += 2;
            if ($esTitulo && $nPalabras === 1) $scoreApellidos += 1;
            if (mb_strlen($valor) > 40) $scoreApellidos -= 2;

            $scoreCurso = 0;
            if ($esCursoKeyword) $scoreCurso += 4;
            if ($esTodoCaps && $nPalabras === 1 && mb_strlen($valor) <= 12) $scoreCurso += 2;
            if ($esTitulo && $nPalabras >= 2) $scoreCurso += 1;
            // Curso suele incluir números o caracteres especiales raros — penaliza valores muy puros
            if (mb_strlen($valor) > 50) $scoreCurso -= 1;

            $matriz[$idx] = [
                'nombre'    => $scoreNombre,
                'apellidos' => $scoreApellidos,
                'curso'     => $scoreCurso,
            ];
        }

        // Asignación greedy: para cada rol, escoger el valor con mayor score
        // que aún no esté asignado. Iteramos roles en orden de "decisión":
        // curso primero (tiene keywords más distintivos), luego apellidos
        // (multi-word title-case suele ser inequívoco), nombre al final.
        $asignacion = [];
        $usados = [];
        foreach (['curso', 'apellidos', 'nombre'] as $rol) {
            $mejorIdx = null;
            $mejorScore = PHP_INT_MIN;
            foreach ($matriz as $idx => $scores) {
                if (isset($usados[$idx])) continue;
                if ($scores[$rol] > $mejorScore) {
                    $mejorScore = $scores[$rol];
                    $mejorIdx = $idx;
                }
            }
            if ($mejorIdx !== null && $mejorScore > 0) {
                $asignacion[$rol] = $mejorIdx;
                $usados[$mejorIdx] = true;
            }
        }

        return $asignacion;
    }

    private function extraerSexoDeTexto(string $texto): ?string
    {
        // Buscar "Sexo: H {marca} M" o "Sexo: H M {marca}"
        // En texto de plantilla solo aparece "Sexo: H M" — sin marca distinguible.
        // Aquí solo extraemos si hay indicación clara.
        if (preg_match('/Sexo\s*:?\s*H\s*[X✓✔☒]/iu', $texto)) {
            return 'H';
        }
        if (preg_match('/Sexo\s*:?\s*[HM\s]*M\s*[X✓✔☒]/iu', $texto)) {
            return 'M';
        }
        return null;
    }

    // ──────────────────────────────────────────────────────────────
    //  Extracción por texto plano (fallback)
    // ──────────────────────────────────────────────────────────────

    /**
     * @param array<int,string> $inferidos
     * @param array<int,string> $avisos
     */
    /**
     * Extracción de campos optimizada para texto producido por Tesseract OCR.
     * Estrategia híbrida:
     *  1. Pre-limpieza de artefactos comunes (©/®/° → eliminar, colapsar espacios).
     *  2. Búsqueda GLOBAL por patrón para campos identificables sin ambigüedad
     *     (NIF, NISS, fecha nacimiento, CIF, teléfono, email con tolerancia OCR).
     *  3. Búsqueda LINE-BASED para campos con label (nombre, apellidos, curso,
     *     cargo, empresa, estudios, categoría, TGSS). Cada par "Label: valor" se
     *     resuelve dentro de la misma línea de texto, evitando capturas largas.
     *
     * @param array<int,string> $inferidos
     * @param array<int,string> $avisos
     * @return array<string,mixed>
     */
    private function extraerCamposOcr(string $texto, array &$inferidos, array &$avisos): array
    {
        $datos = array_fill_keys(self::TODOS_LOS_CAMPOS, null);

        // 1) Pre-limpieza de artefactos OCR comunes
        $texto = $this->limpiarTextoOcr($texto);

        // 2) Búsqueda global por patrones (independiente de labels)
        $datos['nif']              = $this->buscarNifEnOcr($texto);
        $datos['email']            = $this->buscarEmailEnOcr($texto, $avisos);
        $datos['fecha_nacimiento'] = $this->buscarFechaEnOcr($texto);
        $datos['niss']             = $this->buscarNissEnOcr($texto);
        $datos['empresa_cif']      = $this->buscarCifEmpresaEnOcr($texto);
        $datos['telefono']         = $this->buscarTelefonoEnOcr($texto);

        // 3) Búsqueda line-based para campos con label
        foreach (explode("\n", $texto) as $linea) {
            $linea = trim($linea);
            if ($linea === '') continue;

            // Nombre + Apellidos (suelen ir en la misma línea)
            if (empty($datos['nombre']) && preg_match('/Nombre\s*[:\s]+\s*(.+?)\s+Apellidos\s*[:\s]+\s*(.+?)\s*$/iu', $linea, $m)) {
                $datos['nombre'] = $this->limpiarValorOcr($m[1]);
                [$ap1, $ap2] = $this->separarApellidosPdf($this->limpiarValorOcr($m[2]));
                $datos['apellido1'] = $ap1;
                $datos['apellido2'] = $ap2;
                continue;
            }
            if (empty($datos['nombre']) && preg_match('/^Nombre\s*[:\s]+\s*(.+?)\s*$/iu', $linea, $m)) {
                $datos['nombre'] = $this->limpiarValorOcr($m[1]);
            }
            if (empty($datos['apellido1']) && preg_match('/^Apellidos\s*[:\s]+\s*(.+?)\s*$/iu', $linea, $m)) {
                [$ap1, $ap2] = $this->separarApellidosPdf($this->limpiarValorOcr($m[1]));
                $datos['apellido1'] = $ap1;
                $datos['apellido2'] = $ap2;
            }

            // Curso + Horas (línea: "Curso: ... 50 Horas")
            if (empty($datos['curso_pdf']) && preg_match('/^Curso\s*[:\s]+\s*(.+?)\s+(\d{1,4})\s*Horas?\b/iu', $linea, $m)) {
                $datos['curso_pdf'] = trim($m[1]);
                $datos['horas_pdf'] = (int) $m[2];
                continue;
            }
            if (empty($datos['curso_pdf']) && preg_match('/^Curso\s*[:\s]+\s*(.+?)\s*$/iu', $linea, $m)) {
                $datos['curso_pdf'] = trim($m[1]);
            }

            // Cargo y titulación
            if (empty($datos['cargo']) && preg_match('/Cargo\s*y?\s*titulaci[oó]n\s*[:\s]+\s*(.+?)\s*(?:\||$)/iu', $linea, $m)) {
                $datos['cargo'] = $this->limpiarValorOcr($m[1]);
            }

            // Empresa (puede ir seguida de "| CIF: ..."). Case-sensitive en
            // "Empresa" para no matchear el cuerpo "...la empresa que..."
            if (empty($datos['empresa_razon_social']) && preg_match('/Empresa\s*:\s*(.+?)\s*(?:\||CIF\s*:|$)/u', $linea, $m)) {
                $datos['empresa_razon_social'] = $this->limpiarValorOcr($m[1]);
            }

            // Estudios + Grupo Profesional (línea: "Estudios Universitarios Grupo Profesional: ...")
            if (empty($datos['nivel_estudios']) && preg_match('/Estudios\s+(.+?)\s+Grupo\s*Profesional\s*[:\s]+\s*(.+?)\s*$/iu', $linea, $m)) {
                $estudiosRaw = $this->limpiarValorOcr($m[1]);
                $catRaw      = $this->limpiarValorOcr($m[2]);
                [$nivelCod, $nivelInf] = $this->mapearNivelEstudiosPdf($estudiosRaw);
                $datos['nivel_estudios'] = $nivelCod;
                if ($nivelInf) {
                    $inferidos[] = 'nivel_estudios';
                }
                $datos['categoria_profesional'] = $this->mapearCategoriaProfesionalPdf($catRaw);
            }

            // Grupo cotización TGSS (línea con paréntesis explicativos)
            if (empty($datos['grupo_cotizacion_tgss']) && preg_match('/Grupo\s+de\s+cotizaci[oó]n\s+TGSS[^:]*?[:\s]+\s*(.+?)(?:\s*\(|$)/iu', $linea, $m)) {
                $datos['grupo_cotizacion_tgss'] = $this->mapearGrupoCotizacionPdf($this->limpiarValorOcr($m[1]));
            }
        }

        return $datos;
    }

    private function limpiarTextoOcr(string $texto): string
    {
        // Caracteres comunes mal reconocidos cerca de ":"
        $texto = str_replace(['©', '®', '°', '¢', '£'], ' ', $texto);
        // Pipe vertical extraño con espacios
        $texto = preg_replace('/\s+\|\s+/u', ' | ', $texto);
        // Colapsar espacios horizontales pero preservar saltos de línea
        $texto = preg_replace('/[^\S\n]+/u', ' ', $texto);
        return trim($texto);
    }

    private function limpiarValorOcr(?string $valor): string
    {
        if ($valor === null) return '';
        // Quitar puntuación/símbolos basura al inicio y final
        return trim($valor, " \t\n\r\0\x0B,;:|©®°\"'`*¿?¡!");
    }

    private function buscarNifEnOcr(string $texto): ?string
    {
        if (preg_match('/NIF\s*\/?\s*NIE\s*[:\s]+\s*([XYZ]?\d{7,8}\s*[A-Za-z])/iu', $texto, $m)) {
            return $this->normalizarNif($m[1]);
        }
        if (preg_match('/\b([XYZ]\d{7}[A-Za-z]|\d{8}[A-Za-z])\b/u', $texto, $m)) {
            return $this->normalizarNif($m[1]);
        }
        return null;
    }

    private function buscarEmailEnOcr(string $texto, array &$avisos): ?string
    {
        // Patrón normal: caracter@dominio.tld
        if (preg_match('/[\w\.\-]+@[\w\.\-]+\.[a-z]{2,}/iu', $texto, $m)) {
            $e = mb_strtolower(trim($m[0]));
            if (filter_var($e, FILTER_VALIDATE_EMAIL)) {
                return $e;
            }
        }
        // OCR misreads: @ ↔ G/E/C/0/Q. Buscamos cerca del label E-Mail.
        if (preg_match('/E\s*[-\s]?\s*Mail\s*[:\s]+\s*([\w\.\-]+[GECOQ0][\w\.\-]+\.[a-z]{2,})/iu', $texto, $m)) {
            $crudo = $m[1];
            foreach (['G', 'E', 'C', 'O', 'Q', '0'] as $candidato) {
                $pos = stripos($crudo, $candidato);
                if ($pos === false) continue;
                $intento = mb_strtolower(substr_replace($crudo, '@', $pos, 1));
                if (filter_var($intento, FILTER_VALIDATE_EMAIL)) {
                    $avisos[] = "Email reconstruido vía OCR (carácter '{$candidato}' → '@'): \"{$intento}\". Verifícalo.";
                    return $intento;
                }
            }
            $avisos[] = "Email OCR ambiguo: \"{$crudo}\". Corrígelo manualmente.";
        }
        return null;
    }

    private function buscarFechaEnOcr(string $texto): ?string
    {
        if (preg_match('/Fecha\s+de\s+Nacimiento\s*[:\s]+\s*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/iu', $texto, $m)) {
            return $this->normalizarFecha($m[1]);
        }
        // Fallback: cualquier fecha plausible de nacimiento (año 1900-2020)
        if (preg_match('/\b(\d{1,2}[\/\-]\d{1,2}[\/\-](?:19\d{2}|20[01]\d))\b/', $texto, $m)) {
            return $this->normalizarFecha($m[1]);
        }
        return null;
    }

    private function buscarNissEnOcr(string $texto): ?string
    {
        // Patrón: "Seguridad Social ... : 07 1076826283" (12 dígitos con separadores)
        if (preg_match('/Seguridad\s+Social[^:\n]*?[:\s]+\s*((?:\d[\s\-\/]?){11,13})/iu', $texto, $m)) {
            $solo = preg_replace('/\D/', '', $m[1]);
            if (strlen($solo) === 12) {
                return $solo;
            }
            if (strlen($solo) === 11) {
                return $solo;
            }
        }
        // Fallback: secuencia de 12 dígitos consecutivos o separados por espacios
        if (preg_match('/\b(\d{2}\s?\d{10}|\d{12})\b/', $texto, $m)) {
            $solo = preg_replace('/\D/', '', $m[1]);
            if (strlen($solo) === 12) return $solo;
        }
        return null;
    }

    private function buscarCifEmpresaEnOcr(string $texto): ?string
    {
        // CIF cerca del label "CIF:" tras una empresa
        if (preg_match('/(?:Empresa[^\n]*?)?CIF\s*[:\s]+\s*([A-HJNPQRSUVW][\-\s]?\d{8})/iu', $texto, $m)) {
            return $this->normalizarCif($m[1]);
        }
        // Fallback: cualquier CIF empresa en el texto
        if (preg_match_all('/\b([A-HJNPQRSUVW][\-]?\d{8})\b/u', $texto, $matches)) {
            // Si hay más de uno, preferir el último (suele ser el del alumno)
            return $this->normalizarCif(end($matches[1]));
        }
        return null;
    }

    private function buscarTelefonoEnOcr(string $texto): ?string
    {
        if (preg_match('/Tel(?:[eé]fono)?\s*[:\s]+\s*([\d\s\-\.\(\)]{9,18})/iu', $texto, $m)) {
            $tel = preg_replace('/\D/', '', $m[1]);
            if (strlen($tel) >= 9 && strlen($tel) <= 12) {
                return $tel;
            }
        }
        return null;
    }

    private function extraerCamposTextoPlano(string $texto, array &$inferidos, array &$avisos): array
    {
        $datos = array_fill_keys(self::TODOS_LOS_CAMPOS, null);

        $labelsSiguientes = [
            'nombre'           => ['Apellidos'],
            'apellidos'        => ['Curso'],
            'curso'            => ['Horas', 'Tel'],
            'tel'              => ['E-Mail', 'Email', 'E Mail'],
            'email'            => ['Fecha de Nacimiento', 'Fecha'],
            'fecha_nacimiento' => ['NIF/NIE', 'NIF', 'Sexo'],
            'nif'              => ['Sexo'],
            'sexo'             => ['Nº de la Seguridad Social', 'Seguridad Social'],
            'niss'             => ['Grupo de cotización', 'Grupo de cotizaci'],
            'tgss'             => ['Estudios'],
            'estudios'         => ['Grupo Profesional'],
            'grupo_prof'       => ['Cargo'],
            'cargo'            => ['Empresa'],
            'empresa'          => ['CIF'],
            'cif'              => ['En cumplimiento', 'Confirmo'],
        ];

        $datos['nombre']    = $this->extraerCampoEntreLabels($texto, ['Nombre'], $labelsSiguientes['nombre']);
        $apellidos          = $this->extraerCampoEntreLabels($texto, ['Apellidos'], $labelsSiguientes['apellidos']);
        if ($apellidos) {
            [$ap1, $ap2] = $this->separarApellidosPdf($apellidos);
            $datos['apellido1'] = $ap1;
            $datos['apellido2'] = $ap2;
        }
        $datos['curso_pdf'] = $this->extraerCampoEntreLabels($texto, ['Curso'], $labelsSiguientes['curso']);
        $horasRaw           = $this->extraerCampoEntreLabels($texto, ['Horas'], $labelsSiguientes['tel']);
        if ($horasRaw && preg_match('/(\d+)/', $horasRaw, $m)) {
            $datos['horas_pdf'] = (int) $m[1];
        }
        $datos['telefono'] = $this->normalizarTelefono(
            $this->extraerCampoEntreLabels($texto, ['Tel'], $labelsSiguientes['tel'])
        );
        $datos['email'] = $this->normalizarEmail(
            $this->extraerCampoEntreLabels($texto, ['E-Mail', 'Email', 'E Mail'], $labelsSiguientes['email']),
            $avisos
        );
        $datos['fecha_nacimiento'] = $this->normalizarFecha(
            $this->extraerCampoEntreLabels($texto, ['Fecha de Nacimiento'], $labelsSiguientes['fecha_nacimiento'])
        );
        $datos['nif'] = $this->normalizarNif(
            $this->extraerCampoEntreLabels($texto, ['NIF/NIE', 'NIF'], $labelsSiguientes['nif'])
        );
        $datos['sexo'] = $this->extraerSexoDeTexto($texto);

        $datos['niss'] = $this->normalizarNiss(
            $this->extraerCampoEntreLabels($texto, ['Nº de la Seguridad Social', 'Seguridad Social'], $labelsSiguientes['niss'])
        );

        $tgssRaw = $this->extraerCampoEntreLabels($texto, ['Grupo de cotización TGSS', 'Grupo de cotizaci'], $labelsSiguientes['tgss']);
        if ($tgssRaw) {
            $tgssRaw = preg_replace('/n[º°o]?\s*del?\s*1\s*al\s*11.*?:?\s*/iu', '', $tgssRaw);
        }
        $datos['grupo_cotizacion_tgss'] = $this->mapearGrupoCotizacionPdf($tgssRaw);

        $estudiosRaw = $this->extraerCampoEntreLabels($texto, ['Estudios'], $labelsSiguientes['estudios']);
        [$nivelCod, $nivelInferido] = $this->mapearNivelEstudiosPdf($estudiosRaw);
        $datos['nivel_estudios'] = $nivelCod;
        if ($nivelInferido) {
            $inferidos[] = 'nivel_estudios';
        }

        $catRaw = $this->extraerCampoEntreLabels($texto, ['Grupo Profesional'], $labelsSiguientes['grupo_prof']);
        $datos['categoria_profesional'] = $this->mapearCategoriaProfesionalPdf($catRaw);

        $datos['cargo'] = $this->extraerCampoEntreLabels($texto, ['Cargo y titulación', 'Cargo'], $labelsSiguientes['cargo']);
        $datos['empresa_razon_social'] = $this->extraerCampoEntreLabels($texto, ['Empresa'], $labelsSiguientes['empresa']);
        $datos['empresa_cif'] = $this->normalizarCif(
            $this->extraerCampoEntreLabels($texto, ['CIF'], $labelsSiguientes['cif'])
        );

        return $datos;
    }

    /**
     * Extrae el valor entre uno de los labels y el siguiente label conocido.
     */
    private function extraerCampoEntreLabels(string $texto, array $labelsBuscados, array $labelsSiguientes): ?string
    {
        foreach ($labelsBuscados as $label) {
            $labelEscapado = $this->escaparLabelRegex($label);

            $lookahead = '';
            if (!empty($labelsSiguientes)) {
                $lookahead = '(?=' . implode('|', array_map(
                    fn($l) => $this->escaparLabelRegex($l),
                    $labelsSiguientes
                )) . '|\z)';
            } else {
                $lookahead = '(?=\z)';
            }

            $patron = '/' . $labelEscapado . '\s*:?\s*(.+?)\s*' . $lookahead . '/uis';
            if (preg_match($patron, $texto, $m)) {
                $valor = trim(preg_replace('/\s+/u', ' ', $m[1]));
                if ($valor === '' || $this->pareceLabel($valor, $labelsSiguientes)) {
                    continue;
                }
                return $valor;
            }
        }

        return null;
    }

    private function escaparLabelRegex(string $label): string
    {
        $escapado = preg_quote($label, '/');
        $escapado = preg_replace('/\\\\ /', '\\s+', $escapado);
        return $escapado;
    }

    private function pareceLabel(string $valor, array $labels): bool
    {
        foreach ($labels as $l) {
            if (stripos($valor, $l) === 0) {
                return true;
            }
        }
        return false;
    }

    // ──────────────────────────────────────────────────────────────
    //  Normalizaciones específicas por campo
    // ──────────────────────────────────────────────────────────────

    private function normalizarTelefono(?string $valor): ?string
    {
        if (!$valor) return null;
        $tel = preg_replace('/\D/', '', $valor);
        return $tel ?: null;
    }

    private function normalizarEmail(?string $valor, array &$avisos): ?string
    {
        if (!$valor) return null;

        $e = trim($valor);
        // Normalizar artefactos AcroForm: U+FF0A → *, U+FF20 → @
        $e = strtr($e, ['＊' => '*', '＠' => '@']);

        if (strpos($e, '@') === false && strpos($e, '*') !== false) {
            $e = preg_replace('/\*/', '@', $e, 1);
        }

        if (strpos($e, '@') === false && preg_match('/^([\w\.\-]+)\s+([\w\.\-]+\.\w{2,})$/u', $e, $m)) {
            $e = $m[1] . '@' . $m[2];
        }

        if (substr_count($e, '@') > 1) {
            $primera = strpos($e, '@');
            $ultima  = strrpos($e, '@');
            $e = substr($e, 0, $primera) . '@' . substr($e, $ultima + 1);
        }

        $e = mb_strtolower($e);
        $e = trim($e, " \t\n\r\0\x0B,;:");

        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) {
            $avisos[] = "Email del PDF no válido: \"{$valor}\". Corrígelo manualmente.";
            return null;
        }

        return $e;
    }

    private function normalizarFecha(?string $valor): ?string
    {
        if (!$valor) return null;

        if (preg_match('/(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})/', $valor, $m)) {
            $d = (int) $m[1];
            $mes = (int) $m[2];
            $y = (int) $m[3];
            if ($y < 100) {
                $y += $y < 30 ? 2000 : 1900;
            }
            try {
                return Carbon::createFromDate($y, $mes, $d)->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function normalizarNiss(?string $valor): ?string
    {
        if (!$valor) return null;
        $niss = preg_replace('/\D/', '', $valor);
        return $niss !== '' ? $niss : null;
    }

    private function separarApellidosPdf(string $apellidos): array
    {
        $apellidos = preg_replace('/\s+/', ' ', trim($apellidos));
        $partes = explode(' ', $apellidos);

        if (count($partes) === 1) {
            return [$partes[0], null];
        }
        if (count($partes) === 2) {
            return [$partes[0], $partes[1]];
        }
        return [$partes[0], implode(' ', array_slice($partes, 1))];
    }

    private function normalizarSaltos(string $texto): string
    {
        return preg_replace('/[^\S\n]+/u', ' ', $texto);
    }

    // ──────────────────────────────────────────────────────────────
    //  Auxiliares de salida
    // ──────────────────────────────────────────────────────────────

    private function detectarFaltantes(array $datos): array
    {
        $faltantes = [];
        foreach (self::CAMPOS_REQUERIDOS as $campo) {
            if (empty($datos[$campo])) {
                $faltantes[] = $campo;
            }
        }
        return $faltantes;
    }

    private function resultadoIlegible(array $avisos = [], string $tipo = 'ilegible'): array
    {
        return [
            'exito'     => false,
            'tipo'      => $tipo === 'desconocido' ? 'ilegible' : $tipo,
            'origen'    => 'ilegible',
            'datos'     => array_fill_keys(self::TODOS_LOS_CAMPOS, null),
            'inferidos' => [],
            'faltantes' => self::CAMPOS_REQUERIDOS,
            'avisos'    => $avisos ?: ['No se pudo extraer texto del PDF. Ingrese los datos manualmente.'],
        ];
    }
}
