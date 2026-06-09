# Pipeline de extracción de Ficha/Encomienda PDF

Documentación técnica del sistema multi-tier de extracción automática de datos de alumno desde PDFs Ficha de Inscripción y Contrato de Encomienda WebCurso 2026.

> **Estado**: entregado 2026-06-01. Fases 1 (parser base + UI) y 2 (Tier 3 OCR) completadas.
> **Tests**: 6/6 en [`tests/Feature/Webcurso/PdfFichaInscripcionParserTest.php`](../tests/Feature/Webcurso/PdfFichaInscripcionParserTest.php).
> **Fixtures reales**: 5 PDFs en [`tests/Fixtures/pdfs/`](../tests/Fixtures/pdfs/) que cubren todos los formatos vistos en producción.

## Motivación

Antes del pipeline, incorporar un alumno desde un PDF era un proceso 100% manual:
1. El cliente enviaba el PDF firmado por email o WhatsApp.
2. La admin abría el PDF, copiaba cada campo individualmente al formulario Web del Panel.
3. Multiplicado por 10-50 alumnos al día → cuello de botella de 30-60 min/día solo en transcripción.

Existían dos vías automáticas:
- **Formulario individual** (ya implementado, requiere tipeo manual).
- **Subida masiva Excel** (existía pero el cliente raramente enviaba Excel, casi siempre PDF firmado).

El pipeline añade la **cuarta vía**: subir el/los PDFs, ver preview prellenado, confirmar.

## Formatos de PDF soportados

| Formato | Páginas | Contenido del alumno | Origen típico |
|---|---|---|---|
| **Ficha de Inscripción** | 1 | Toda la página | Cliente rellena AcroForm en PC o app |
| **Contrato de Encomienda WebCurso 2026** | 3 | Página 2 (p.1 = empresa firmante, p.3 = cláusulas) | Igual al anterior + firma |

Autodetección: se detecta por contenido (`stripos` de "CONTRATO DE ENCOMIENDA" vs "FICHA DE INSCRIPCI") y como fallback por número de páginas.

## Pipeline 4-tier

```
PDF subido (multi-archivo, hasta 20)
   │
   ▼
┌─────────────────────────────────────────────────────────────┐
│ Tier 1 — AcroForm                                            │
│   • Widget V de la página del alumno (extraerCamposDesdeWidgets)  │
│   • Pool XObject/Form (extraerCamposAcroForm)                │
│     - Filtrado de cluster admin por anclas únicas             │
│     - Sort DESC por ID para últimas ediciones                 │
│     - Scoring greedy para nombre/apellidos/curso              │
│   • Fusión: pool gana en patternables, widget V en scored     │
└────────────────────┬────────────────────────────────────────┘
                     │ ¿extrae datos?
                     ▼
                     SÍ → return (origen='acroform')
                     NO ↓
┌─────────────────────────────────────────────────────────────┐
│ Tier 2 — Capa de texto plana                                 │
│   • extraerCamposTextoPlano sobre $pdf->getText()            │
│   • Regex con label anchors + lookaheads                     │
└────────────────────┬────────────────────────────────────────┘
                     │ ¿extrae datos?
                     ▼
                     SÍ → return (origen='acroform' — Tier 2 se considera AcroForm-equivalente)
                     NO ↓
┌─────────────────────────────────────────────────────────────┐
│ Tier 3 — OCR local (Tesseract)                               │
│   • PdfOcrExtractor: PDF → PNG vía Imagick (300 DPI grayscale)│
│   • Tesseract con lang spa+eng, PSM 6                        │
│   • extraerCamposOcr: line-based + patrones globales          │
│     - Reconstrucción de email (@ ↔ G/E/C/O/Q/0)              │
│     - Patrones tolerantes (Nº → N®, espacios irregulares)    │
└────────────────────┬────────────────────────────────────────┘
                     │ ¿OCR text >= 200 chars Y se encontró NIF o email?
                     ▼
                     SÍ → return (origen='ocr', aviso para revisar)
                     NO ↓
┌─────────────────────────────────────────────────────────────┐
│ Tier 4 — Ilegible                                            │
│   • Preview con campos vacíos                                │
│   • PDF archivado igualmente (custodia FUNDAE 4 años)        │
│   • Admin transcribe a mano                                  │
└─────────────────────────────────────────────────────────────┘
```

## Servicios

### `App\Services\Webcurso\PdfFichaInscripcionParser`

Orquestador del pipeline. Único punto de entrada para los consumidores.

```php
public function parsear(string $filePath): array
```

Retorna estructura consistente:

```php
[
  'exito'     => bool,                                       // true salvo Tier 4
  'tipo'      => 'ficha' | 'encomienda' | 'ilegible',
  'origen'    => 'acroform' | 'ocr' | 'ilegible',
  'datos'     => [                                           // claves siempre presentes (null si no extraído)
    'nombre', 'apellido1', 'apellido2',
    'nif', 'email', 'telefono',
    'fecha_nacimiento',                                      // formato Y-m-d
    'sexo',                                                  // H | M | null
    'niss',
    'grupo_cotizacion_tgss',                                 // '1' a '11'
    'nivel_estudios',                                        // 1 a 10
    'categoria_profesional',                                 // 1 a 5
    'cargo',
    'empresa_razon_social',
    'empresa_cif',
    'curso_pdf',                                             // solo informativo
    'horas_pdf',                                             // solo informativo
  ],
  'inferidos' => ['nivel_estudios', ...],                    // UI los muestra en amarillo
  'faltantes' => ['email', ...],                             // requeridos sin valor → UI en rojo
  'avisos'    => ['CIF del PDF no coincide ...', ...],
]
```

### `App\Services\Webcurso\PdfOcrExtractor`

Tier 3. Independiente del parser principal — puede usarse standalone para obtener texto OCR de cualquier PDF.

```php
public function extraerTexto(string $pdfPath, string $tipo = 'desconocido'): string
```

Pasos internos:
1. Validar archivo + dependencias (`tesseract --version` ejecutable, Imagick + TesseractOCR cargadas).
2. Determinar páginas a procesar según `$tipo` (Encomienda p.2, Ficha p.1, desconocido p.1-N).
3. Renderizar cada página con Imagick a PNG temporal (300 DPI, `IMGTYPE_GRAYSCALE`).
4. Aplicar `(new TesseractOCR($png))->lang('spa','eng')->psm(6)->run()` por página.
5. Concatenar texto, limpiar archivos temporales (en `finally`).
6. Fail-soft: cualquier excepción → log + return `''`.

Configuración via `config/pdf_ocr.php` (DPI, lang, PSM, max_pages, min_chars).

## Decisiones técnicas no obvias

### 1. Widget V vs XObject DESC para PDFs editados múltiples veces

**Problema**: cuando el usuario edita un campo (ej. email) en un visor de PDF como Adobe Reader o el visor del navegador, suele guardar el nuevo valor visualmente (nuevo XObject appearance stream con ID alto) **pero NO actualiza el campo `/V` del widget**. El resultado: el widget `/V` queda con el primer valor, mientras los XObjects acumulan todas las versiones editadas con IDs crecientes.

**Caso real detectado**: usuario edita email 3 veces: `barreto4444@gmail.com` → `barreto5@gmail.com` → `barreto12365@gmail.com`. Pool de XObjects tenía 3 valores con IDs 561, 565, 569. Widget V seguía con `BARRETO5@GMAIL.COM`.

**Solución**:
- Para campos patternables (email, NIF, NISS, fecha, teléfono, CIF, dropdowns, horas): el pool de XObject gana sobre widget V. Iteración DESC por ID en `extraerCamposAcroForm` para que el primer match sea el de mayor ID = última versión.
- Para campos sin patrón claro (nombre, apellidos, cargo, curso, empresa): widget V gana cuando está poblado. El pool itera ASC para tie-breaking estable en `asignarNombreApellidosCurso`.

Implementación en `extraerCamposAcroForm`:
```php
uksort($valores, fn($a,$b) => ((int) preg_replace(...,'',$b)) <=> ((int) preg_replace(...,'',$a)));  // DESC
$pool = array_values($valores);
// Tomar primer match per campo (= mayor ID = última edición)
$tomar(fn($v) => preg_match('/[\w\.\-]+@[\w\.\-]+\.[a-z]{2,}/iu', $v));
// ...
// Después, para nombre/apellidos/curso, reordenar restantes ASC
$restantes = array_reverse($restantes, true);  // vuelve a ASC
$asignacion = $this->asignarNombreApellidosCurso($restantes);
```

### 2. Filtrado del cluster admin por anclas únicas (Encomienda)

**Problema**: la página 1 de la Encomienda contiene los datos del firmante (admin de la empresa): su nombre, NIF, dirección, etc. Sus appearance XObjects pollute el pool. El enfoque ingenuo "remover una ocurrencia por valor V de página 1" falla cuando hay valores compartidos (ej. la razón social aparece tanto en p.1 como en p.2 del Contrato de Encomienda).

**Solución**: identificar el cluster admin como un rango de IDs.

```
1. Para cada widget V de página 1, buscar su contenido en el pool.
2. Si el contenido aparece en UNA sola posición → ese ID es admin (ancla única).
3. min(idsAncla) - margen ≤ cluster admin ≤ max(idsAncla) + margen.
4. Eliminar todos los pool entries en ese rango.
```

Si el contenido aparece en N>1 posiciones, no podemos discriminar con esa ancla — pasamos a la siguiente. Si NO hay anclas únicas, fallback al método ingenuo (remover una ocurrencia por valor, ID ASC).

Caso real: Ejemplo 5 (Greicy Barreto). Admin "JOSE VICENTE MORELL MACIA" en p.1 = único en pool (sólo en ID 430). NIF admin 74240865Q único en 432. Dirección y email admin únicos. Rango admin = [380, 493]. Alumno IDs en 30-52 + ediciones en 561-569 → preservados.

### 3. Resolución del cluster alumno + ediciones tardías

Después del filtrado admin, el pool puede tener:
- El cluster alumno original (IDs bajos típicamente).
- Ediciones posteriores que el usuario haya hecho a campos del alumno (IDs muy altos, lejos del cluster).

`filtrarPoolALaPaginaDelAlumno` usa los valores de widget V conocidos como anclas y conserva:
- `[anchorMin - margen, anchorMax + margen]` → cluster alumno original
- `> anchorMax + 500` → posibles ediciones tardías

Esto permite que las ediciones de email (IDs 561-569 en el caso de Ejemplo 5 editado) se preserven aunque el cluster alumno original esté en IDs bajos (30-52).

### 4. Reconstrucción de email desde artefactos

**AcroForm**: algunos PDFs (Ejemplo 2) muestran `@` como `*` (codificación de fuente). Reemplazamos:
- `＊` (U+FF0A) → `*`
- `＠` (U+FF20) → `@`
- Si tiene `*` sin `@` → reemplazar primer `*` por `@`
- Si tiene espacio sin `@` y matchea `^[\w\.\-]+\s+[\w\.\-]+\.\w{2,}$` → reemplazar primer espacio por `@`

**OCR (Tesseract)**: el `@` se confunde con `G`, `E`, `C`, `O`, `Q`, `0` según la fuente del PDF. Estrategia en `buscarEmailEnOcr`:
```php
// 1. Intentar pattern email normal
if (preg_match('/[\w\.\-]+@[\w\.\-]+\.[a-z]{2,}/iu', $texto, $m)) { ... }

// 2. Cerca del label E-Mail, buscar local+G/E/C/O/Q/0+dominio.tld
if (preg_match('/E\s*Mail\s*[:\s]+\s*([\w\.\-]+[GECOQ0][\w\.\-]+\.[a-z]{2,})/iu', $texto, $m)) {
    foreach (['G','E','C','O','Q','0'] as $cand) {
        $pos = stripos($crudo, $cand);
        $intento = mb_strtolower(substr_replace($crudo, '@', $pos, 1));
        if (filter_var($intento, FILTER_VALIDATE_EMAIL)) {
            $avisos[] = "Email reconstruido via OCR ('$cand' → '@'): $intento. Verifícalo.";
            return $intento;
        }
    }
}
```

Caso real: OCR de `laboral@nartha.es` da `laboralGnartha.es` → se reconstruye correctamente.

### 5. Title Case selectivo

PDFs llegan típicamente con todos los datos en MAYÚSCULAS. La UI debe mostrar nombres en case más natural ("Greicy Lisbeth" en vez de "GREICY LISBETH").

Aplicamos Title Case (`aTitleCase`, unicode-safe con `mb_strtolower` + ucfirst por palabra) **solo a**:
- `nombre`, `apellido1`, `apellido2`, `cargo`

**Excluidos** (preservar la grafía original):
- `empresa_razon_social` — suele tener siglas "S.L.", "NCS", "S.A.U." que título-casear rompe.
- `curso_pdf` — nombres de marca como "ChatGPT", "Excel 365" no deben title-casearse.

### 6. Guard "sin NIF NI email → ilegible" en Tier 3

**Problema**: Tesseract sobre manuscritos (Ejemplo 4) produce garbage que coincide ocasionalmente con patrones de los mappings (ej. "Trabajadores menores de 18 años" del template explanatory text matchea código TGSS=11). Sin guard, devolveríamos exito=true con datos falsos.

**Solución**: NIF y email son los dos campos con patrones más distintivos (formato Y123456J o local@dominio.tld). Si OCR no logra encontrar NINGUNO de los dos, el texto OCR es probablemente basura → return `resultadoIlegible()`.

```php
if (empty($datos['nif']) && empty($datos['email'])) {
    return $this->resultadoIlegible([
        'OCR no logró identificar datos clave (NIF/email). Posible manuscrito o escaneo de baja calidad — completa los datos manualmente.',
    ]);
}
```

Ejemplo 4 (manuscrito) cae a ilegible. Ejemplo 3 (impreso) pasa el guard porque NIF y email se extraen correctamente.

### 7. Por qué Tesseract y no Vision API (Claude/OpenAI/Document AI)

Comparativa al elegir el provider de Tier 3:

| Criterio | Tesseract local | Claude Vision | OpenAI GPT-4o-mini | Document AI |
|---|---|---|---|---|
| Coste/PDF | **$0** | $0.02-0.05 | $0.01-0.03 | $0.03 |
| GDPR | **✓ Local** | ⚠ Anthropic ZDR | ⚠ OpenAI ZDR | ⚠ GCP (EU disponible) |
| Calidad impreso | 90-95% | 98-99% | 97% | 99% |
| Calidad manuscrito | 0-10% | 50-70% | 40-60% | 60-80% |
| Latencia | 1-3s | 3-8s | 2-5s | 2-4s |
| Setup | Dockerfile + 1 pkg composer | SDK + API key | SDK + API key | GCP project + processor |
| Mantenimiento | Stable | Modelo cambia anual | Modelo cambia | Stable |

**Decisión**: Tesseract.
- WebCurso recibe mayoría de PDFs impresos (90-95% accuracy suficiente).
- Cero coste = cero fricción operativa.
- Datos personales no salen del Docker → GDPR sin DPAs externos.
- Manuscritos seguirían cayendo a manual incluso con Vision (calidad insuficiente para confiar).
- Reusa al 95% el parser regex de Tier 2 → superficie de código mínima.

## Persistencia del PDF original

Tras confirmar el preview, el PDF se mueve de `livewire-tmp/` a:
```
storage/app/private/fichas-inscripcion/{grupo_id}/{nif}_{timestamp}.pdf
```

Pivot `grupo_formativo_alumno` con 3 campos nuevos:
- `ficha_inscripcion_path` (string, nullable)
- `ficha_inscripcion_tipo` (enum: ficha | encomienda | manual)
- `ficha_inscripcion_subida_en` (timestamp)

Modelo `GrupoFormativoAlumno` con observer:
```php
protected static function booted(): void
{
    static::deleting(function (self $pivot) {
        if ($pivot->ficha_inscripcion_path) {
            Storage::disk('local')->delete($pivot->ficha_inscripcion_path);
        }
    });
}
```

Descarga vía `MatriculacionPanel::descargarFichaPdf($grupoId, $alumnoId)`:
```php
return Storage::disk('local')->download($pivot->ficha_inscripcion_path);
```

No es URL pública (carpeta `storage/app/private/`, servida solo a través del controller con autorización implícita por `candidato_id` del grupo).

## UI

Toggle "▼ Subir Ficha PDF (Encomienda / Inscripción)" en `MatriculacionPanel` (panel de matriculación de un candidato). Posicionado como tercera ruta de alta tras "Nuevo alumno individual" y "Subida masiva Excel".

### Tabla preview

Una fila por PDF. Cada fila contiene:

**Cabecera**:
- Badge tipo (`Ficha` azul / `Encomienda` indigo / `Ilegible · manual` rojo)
- Badge OCR (`🔍 OCR` naranja) si `origen=ocr`
- Badge estado (`Crear` verde / `Actualizar` ámbar / `Duplicado · ignorado` gris)
- Nombre del archivo (truncate con tooltip)
- Botón `✕ Quitar`

**Avisos** (banner amarillo): warnings del parser (CIF no coincide, email reservado, etc.)

**Campos editables** (16 inputs/selects con `wire:model.lazy`):
- nombre, apellido1, apellido2, NIF, email, teléfono, fecha nac, NISS
- sexo (select H/M)
- grupo cotización TGSS (select 1-11)
- nivel estudios (select 1-10)
- categoría profesional (select 1-5)

Colores de fondo:
- Amarillo (`bg-yellow-50 border-yellow-300`): campo inferido por mapeo ambiguo
- Rojo (`bg-red-50 border-red-300`): campo obligatorio sin valor

Tooltips en filas "Actualizar": `antes: X → ahora: Y`

**Footer informativo**: línea con curso/horas/cargo/empresa detectados (no editables aquí, solo para context).

### Listado de alumnos del grupo

Junto a los botones "Editar" / "Quitar" del alumno, aparece el icono **📎 PDF** clicable si tiene ficha archivada. Descarga el PDF original.

## Configuración y variables de entorno

```env
# Tier 3 OCR
PDF_OCR_ENABLED=true        # default: true
PDF_OCR_DPI=300             # default: 300
PDF_OCR_LANG=spa+eng        # default: spa+eng
PDF_OCR_PSM=6               # default: 6 (uniform block of text)
PDF_OCR_MAX_PAGES=3         # default: 3
PDF_OCR_MIN_CHARS=200       # default: 200
PDF_OCR_TMP_DIR=            # default: sys_get_temp_dir()
```

## Infraestructura

**Composer**:
```
"thiagoalessio/tesseract_ocr": "^2.13"
```

**Dockerfile** (`docker/8.5/Dockerfile`, publicado vía `./vendor/bin/sail artisan sail:publish`):
```dockerfile
apt-get install -y ... tesseract-ocr tesseract-ocr-spa ...
```

Para aplicar:
```bash
./vendor/bin/sail down
./vendor/bin/sail build --no-cache
./vendor/bin/sail up -d
```

Verificación:
```bash
./vendor/bin/sail exec laravel.test tesseract --list-langs
# debe listar: eng, osd, spa
```

## Casos cubiertos por tests

| Fixture | Tier | Origen | Datos esperados |
|---|---|---|---|
| `ejemplo1-encomienda-acroform.pdf` | Tier 1 | acroform | Paul Andre Lupascu / Y2010452J / nivel_estudios=4 |
| `ejemplo2-ficha-acroform.pdf` | Tier 1 | acroform | Alberto Ludeña Peirado, email reconstruido `*→@` |
| `ejemplo3-encomienda-ocr.pdf` | Tier 3 | ocr | Ana Ruiz Molina, email reconstruido `G→@` |
| `ejemplo4-encomienda-manuscrito.pdf` | Tier 4 | ilegible | sin datos (guard NIF+email) |
| `ejemplo5-encomienda-widget-v.pdf` | Tier 1 | acroform | Greicy Lisbeth Barreto Colmenares, widget V poblado con IDs invertidos |

Adicional: PDF inexistente/corrupto → ilegible con aviso.

## Limitaciones conocidas y mejoras futuras

**Limitaciones**:
- Manuscritos (Ejemplo 4): siempre caerán a ilegible. Vision API podría mejorar 50-70% pero el coste no compensa para WebCurso.
- OCR errores comunes que pasan al preview: `30/06` ↔ `30/08`, NIF con letra ambigua, NISS con dígitos confundidos. El admin revisa y corrige.
- Empresa con grafía mixta (ej. "AGRO LAS BAYAS S.L" sin punto final): preserva tal cual, no normaliza.

**Mejoras opcionales (no en este PR)**:
- **Pre-procesamiento de imagen** (deskew, denoise antes de OCR): +5-10% accuracy.
- **Tier 3.5 LLM fallback**: si OCR + regex extrae < 4 campos requeridos, invocar Claude Haiku 4.5 (~$0.001/PDF) para reestructurar. Híbrido coste-eficiente.
- **Cache OCR por hash del PDF**: evita re-OCR de mismo archivo.
- **Entrenamiento Tesseract con plantilla WebCurso**: muestra de 50 escaneos → +2-5% accuracy específico al template.

## Archivos clave

| Archivo | Responsabilidad |
|---|---|
| [`app/Services/Webcurso/PdfFichaInscripcionParser.php`](../app/Services/Webcurso/PdfFichaInscripcionParser.php) | Orquestador del pipeline, 4 tiers |
| [`app/Services/Webcurso/PdfOcrExtractor.php`](../app/Services/Webcurso/PdfOcrExtractor.php) | Tier 3: render + OCR |
| [`app/Console/Commands/Concerns/LegacyMappings.php`](../app/Console/Commands/Concerns/LegacyMappings.php) | Mapeos texto→código FUNDAE (estudios/categoría/TGSS) |
| [`app/Livewire/Webcurso/MatriculacionPanel.php`](../app/Livewire/Webcurso/MatriculacionPanel.php) | UI: `procesarPdfs`, `confirmarSubidaPdf`, `descargarFichaPdf` |
| [`app/Models/GrupoFormativoAlumno.php`](../app/Models/GrupoFormativoAlumno.php) | Pivot con 3 campos nuevos + observer de cleanup |
| [`config/pdf_ocr.php`](../config/pdf_ocr.php) | Config Tier 3 |
| [`docker/8.5/Dockerfile`](../docker/8.5/Dockerfile) | Tesseract + Spanish lang pack |
| [`tests/Feature/Webcurso/PdfFichaInscripcionParserTest.php`](../tests/Feature/Webcurso/PdfFichaInscripcionParserTest.php) | 6 tests con fixtures reales |
| [`tests/Fixtures/pdfs/`](../tests/Fixtures/pdfs/) | 5 PDFs ejemplo (uno por escenario) |
