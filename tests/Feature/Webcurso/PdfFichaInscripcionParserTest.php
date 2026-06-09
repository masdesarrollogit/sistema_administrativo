<?php

use App\Services\Webcurso\PdfFichaInscripcionParser;

/**
 * Tests del parser usando 4 PDFs reales de candidatos como fixtures:
 *  - Ejemplo 1: Contrato Encomienda WebCurso 2026 con AcroForm digital (PAUL LUPASCU)
 *  - Ejemplo 2: Ficha de Inscripción WebCurso con AcroForm digital (Alberto Ludeña, artefacto * en email)
 *  - Ejemplo 3: Contrato Encomienda escaneado CON capa OCR (Ana Ruiz)
 *  - Ejemplo 4: Contrato Encomienda escaneado sin OCR, manuscrito (debe ser ilegible)
 */

function fixturePdf(string $nombre): string
{
    return __DIR__ . '/../../Fixtures/pdfs/' . $nombre;
}

it('parsea Encomienda AcroForm digital (Ejemplo 1 - PAUL LUPASCU)', function () {
    $parser = new PdfFichaInscripcionParser();
    $r = $parser->parsear(fixturePdf('ejemplo1-encomienda-acroform.pdf'));

    expect($r['exito'])->toBeTrue();
    expect($r['tipo'])->toBe('encomienda');

    $d = $r['datos'];
    // Normalización a Title Case: PDFs llegan en MAYÚSCULAS pero la UI muestra
    // "Paul Andre" / "Lupascu" para evitar texto gritado en pantalla.
    expect($d['nombre'])->toBe('Paul Andre');
    expect($d['apellido1'])->toBe('Lupascu');
    expect($d['nif'])->toBe('Y2010452J');
    expect($d['email'])->toBe('paulprade05@gmail.com');
    expect($d['telefono'])->toBe('699002393');
    expect($d['fecha_nacimiento'])->toBe('2005-06-29');
    // Sexo viene como checkbox AcroForm sin link claro a su widget → null,
    // el admin lo completa en el preview.
    expect($d['sexo'])->toBeNull();
    expect($d['niss'])->toBe('261027084165');
    expect($d['grupo_cotizacion_tgss'])->toBe('7'); // Auxiliares administrativos
    expect($d['nivel_estudios'])->toBe(4);          // 2ª etapa ed. Secundaria
    expect($d['categoria_profesional'])->toBe(3);   // Técnico
    expect($d['empresa_cif'])->toBe('B99274383');
    // Empresa NO se title-casea (preserva siglas como S.L., NCS, etc.)
    expect($d['empresa_razon_social'])->toBe('ARAGON NCS, S.L.');
    expect($d['curso_pdf'])->toContain('Claude');
    expect($d['horas_pdf'])->toBe(70);
    expect($r['faltantes'])->toBeEmpty();
});

it('parsea Ficha AcroForm con artefacto * en email (Ejemplo 2 - Alberto Ludeña)', function () {
    $parser = new PdfFichaInscripcionParser();
    $r = $parser->parsear(fixturePdf('ejemplo2-ficha-acroform.pdf'));

    expect($r['exito'])->toBeTrue();
    expect($r['tipo'])->toBe('ficha');

    $d = $r['datos'];
    expect($d['nombre'])->toBe('Alberto');
    expect($d['apellido1'])->toBe('Ludeña');
    expect($d['apellido2'])->toBe('Peirado');
    expect($d['nif'])->toBe('49428110Y');
    // Artefacto: el PDF muestra "alberto.ludena*indalva.com" — debe corregirse a @
    expect($d['email'])->toBe('alberto.ludena@indalva.com');
    expect($d['telefono'])->toBe('647289283');
    expect($d['fecha_nacimiento'])->toBe('1999-09-14');
    // Sexo viene como checkbox AcroForm sin link a su widget → null.
    expect($d['sexo'])->toBeNull();
    expect($d['niss'])->toBe('021024660979');
    expect($d['grupo_cotizacion_tgss'])->toBe('5');  // Oficiales administrativos
    expect($d['nivel_estudios'])->toBe(6);           // Tec sup/FP GRADO SUP → Técnico Superior
    expect($d['categoria_profesional'])->toBe(3);    // Técnico
    expect($d['empresa_cif'])->toBe('B03029634');
    expect($r['faltantes'])->toBeEmpty();
});

it('extrae datos del Ejemplo 3 (escaneo) vía Tier 3 OCR con Tesseract', function () {
    // Ejemplo 3 es un escaneo de impresión (sin AcroForm, sin capa de texto).
    // Tier 3 lo procesa con Tesseract + reglas line-based en extraerCamposOcr.
    // Si Tesseract no está disponible en el entorno (CI o local sin instalar),
    // se salta el test en vez de fallar.
    if (! @exec('tesseract --version 2>&1', $_, $status) || $status !== 0) {
        test()->markTestSkipped('Tesseract no está instalado en este entorno.');
    }

    $parser = new PdfFichaInscripcionParser();
    $r = $parser->parsear(fixturePdf('ejemplo3-encomienda-ocr.pdf'));

    expect($r['exito'])->toBeTrue();
    expect($r['tipo'])->toBe('encomienda');
    expect($r['origen'])->toBe('ocr');

    $d = $r['datos'];
    expect($d['nombre'])->toBe('Ana');
    expect($d['apellido1'])->toBe('Ruiz');
    expect($d['apellido2'])->toBe('Molina');
    expect($d['nif'])->toBe('43220697V');
    // Email se reconstruye desde "laboralGnartha.es" → "laboral@nartha.es"
    expect($d['email'])->toBe('laboral@nartha.es');
    expect($d['fecha_nacimiento'])->toBe('1993-06-30');
    expect($d['niss'])->toBe('071076826283');
    expect($d['grupo_cotizacion_tgss'])->toBe('5');  // Oficiales administrativos
    expect($d['nivel_estudios'])->toBe(7);            // Universitarios → 7 (inferido)
    expect($d['categoria_profesional'])->toBe(4);    // Trabajador Cualificado
    expect($d['cargo'])->toBe('Mando Intermedio');
    expect($d['empresa_razon_social'])->toBe('DIVERTHA FUNCIONS INSULAR');
    expect($d['empresa_cif'])->toBe('B07656465');
    expect($d['horas_pdf'])->toBe(75);
    // Aviso de OCR presente
    expect($r['avisos'])->toContain('Datos extraídos vía OCR. Revisa NIF, NISS, fechas y email — el reconocimiento puede tener errores menores.');
});

it('marca como ilegible un PDF escaneado manuscrito sin OCR (Ejemplo 4)', function () {
    $parser = new PdfFichaInscripcionParser();
    $r = $parser->parsear(fixturePdf('ejemplo4-encomienda-manuscrito.pdf'));

    expect($r['exito'])->toBeFalse();
    expect($r['tipo'])->toBe('ilegible');
    expect($r['datos']['nombre'])->toBeNull();
    expect($r['datos']['nif'])->toBeNull();
    expect($r['faltantes'])->not->toBeEmpty();
    expect($r['avisos'])->not->toBeEmpty();
});

it('parsea Encomienda con widget V poblado (Ejemplo 5 - GREICY BARRETO)', function () {
    // Este PDF se guardó con una herramienta que escribe los valores
    // directamente en /V de cada widget de la página 2. La heurística por
    // cluster de XObject IDs no aplica aquí porque los IDs alumno son BAJOS y
    // los del admin son ALTOS — al revés que en Ejemplo 1. El parser debe leer
    // los widget V primero.
    $parser = new PdfFichaInscripcionParser();
    $r = $parser->parsear(fixturePdf('ejemplo5-encomienda-widget-v.pdf'));

    expect($r['exito'])->toBeTrue();
    expect($r['tipo'])->toBe('encomienda');

    $d = $r['datos'];
    // Nombre, apellidos y cargo se title-casean (PDFs en mayúsculas → "Greicy Lisbeth")
    expect($d['nombre'])->toBe('Greicy Lisbeth');
    expect($d['apellido1'])->toBe('Barreto');
    expect($d['apellido2'])->toBe('Colmenares');
    expect($d['nif'])->toBe('Y5698965');
    expect($d['email'])->toBe('greicy.barreto5@gmail.com');
    expect($d['telefono'])->toBe('69878956');
    expect($d['fecha_nacimiento'])->toBe('1980-09-13');
    expect($d['niss'])->toBe('011148426263');
    expect($d['cargo'])->toBe('Administrador');
    // Empresa no se title-casea (preserva siglas)
    expect($d['empresa_razon_social'])->toBe('AGRO LAS BAYAS S.L');
    expect($d['empresa_cif'])->toBe('B42624494');
    expect($d['curso_pdf'])->toBe('CHATGPT INCIAL');
    expect($d['horas_pdf'])->toBe(50);
    // Dropdowns vía pool XObject (texto del PDF)
    expect($d['grupo_cotizacion_tgss'])->toBe('11');  // Trabajadores menores de 18 años
    expect($d['nivel_estudios'])->toBe(7);            // Universitarios → 7 inferido
    expect($r['inferidos'])->toContain('nivel_estudios');
    expect($d['categoria_profesional'])->toBe(5);    // Trabajador baja cualificación
    // Sexo detectado por par de checkboxes a la misma altura Y; el AS de cada
    // uno indica si está marcado. Greicy → M (mujer).
    expect($d['sexo'])->toBe('M');
});

it('aplica ediciones posteriores del usuario en nombre/apellidos/email (Ejemplo 6 - Demo2)', function () {
    // Este PDF se editó varias veces en un viewer. El widget V quedó
    // obsoleto con los valores originales ("Alumno Demo 1", "WEBCURSO",
    // "greicy.b...") pero los appearance streams XObject acumulan todas
    // las ediciones con IDs altos. El parser debe coger la ÚLTIMA edición:
    // - nombre: "Demo 2" (de XObject 552_0, después de "Alumno Demo 2" en 544_0)
    // - apellidos: "WEBCURSO pEREZ" (XObject 548_0, después de "WEBCURSO" en 29_0)
    // - email: "demo2.b12345689@gmail.com" (XObject 542_0)
    // También el widget T "Profesión y titulación" viene en Latin-1 (byte
    // 0xF3 huérfano), debe convertirse a UTF-8 para que el mapeo encuentre
    // "Administradora" como cargo y no se le caiga al longest-text fallback.
    $parser = new PdfFichaInscripcionParser();
    $r = $parser->parsear(fixturePdf('ejemplo6-encomienda-ediciones-multiples.pdf'));

    expect($r['exito'])->toBeTrue();
    expect($r['tipo'])->toBe('encomienda');

    $d = $r['datos'];
    expect($d['nombre'])->toBe('Demo 2');
    expect($d['apellido1'])->toBe('Webcurso');
    expect($d['apellido2'])->toBe('Perez');
    expect($d['email'])->toBe('demo2.b12345689@gmail.com');
    expect($d['cargo'])->toBe('Administradora');
    expect($d['empresa_razon_social'])->toBe('MIRNAX BIOSENS SL');
    expect($d['empresa_cif'])->toBe('B87513727');
    expect($d['curso_pdf'])->toBe('Chatgpt Inicial');
    expect($d['sexo'])->toBe('M');
});

it('marca como ilegible un PDF inexistente / corrupto', function () {
    $parser = new PdfFichaInscripcionParser();
    $r = $parser->parsear('/tmp/no-existe-este-pdf.pdf');

    expect($r['exito'])->toBeFalse();
    expect($r['tipo'])->toBe('ilegible');
    expect($r['avisos'])->not->toBeEmpty();
});
