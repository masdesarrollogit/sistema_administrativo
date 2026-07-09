# Integración: Contratos de Encomienda (sistema externo) → Candidatos + Alumnos staging en el Panel

> Copia del plan de trabajo (original en `~/.claude/plans/tengo-importar-dos-tablas-snazzy-quill.md`). Última actualización: 2026-07-09.
>
> ## ✅ ESTADO: IMPLEMENTADO (2026-07-09)
> Toda la integración está construida y verificada end-to-end contra **producción remota** (`130.117.90.142`) y el endpoint PDF real. Resumen de lo entregado:
> - Migraciones `encomienda_contratos` + `encomienda_alumnos_staging`; modelos `EncomiendaContrato`, `EncomiendaAlumnoStaging`.
> - `config/encomienda.php` + vars `ENCOMIENDA_DB_*` / `ENCOMIENDA_PDF_*` en `.env`.
> - Mapeos nuevos en `LegacyMappings` (letra→estudios, romano→categoría, cotización, fecha flexible) + tests (4 casos).
> - Comando `encomienda:sincronizar` (`--dry-run`/`--force`), idempotente, con ediciones/borrados; cron cada 30 min.
> - `MatriculacionPanel`: Sección 5 "Alumnos desde encomienda" + `materializarAlumnoEncomienda()` con descarga automática del PDF firmado (gated por config) + tests.
> - Vista `EncomiendaContratosIndex` en `/webcurso/encomienda` con KPIs, filtros, botón "Sincronizar ahora" y enlace en el sidebar + tests.
> - **Verificado**: BD remota (21 contratos), 2 candidatos creados con requisitos, 8 alumnos en staging con mapeos correctos, descarga PDF real (HTTP 200, 90 KB), idempotencia. 8 tests nuevos en verde.
> - Nota: 1 test preexistente ajeno falla (`RegistrationTest`, página Volt de registro) — no relacionado con esta integración.

## Contexto

WebCurso tiene un **nuevo sistema externo** de firma digital de contratos de encomienda (`https://www.webcurso.es/encomienda/contrato-encomienda.php`, PHP + MySQL en el servidor remoto `130.117.90.142`, BD `webcourses2014`). El cliente rellena y firma el contrato online, generando dos tablas en ese servidor:
- `contratos_encomienda` — datos de la **empresa** y del firmante (21 filas hoy).
- `encomienda_alumnos` — datos de los **alumnos** declarados en cada contrato (7 filas hoy).

Ya importamos un snapshot de ambas a la BD local `webcourses2014` (solo para inspeccionar estructura). El objetivo real es **integrar ese flujo en el Panel**: cada vez que aparezca un contrato nuevo, crear automáticamente el **Candidato** y dejar los datos de los alumnos en una **tabla temporal (staging)** para que, al crear el Grupo Formativo, el admin pueda seleccionarlos y materializarlos como Alumnos reales y matricularlos.

Esto añade una **cuarta fuente de captura** de candidatos/alumnos (junto a alta manual, importación bonificados y PDFs Ficha/Encomienda).

## Decisiones confirmadas con la usuaria

1. **Acceso a datos**: conexión **MySQL directa remota** al servidor externo (pull periódico por cron). No webhook, no re-import manual.
2. **Empresa no hallada por CIF**: **dejar el contrato pendiente** (`pendiente_empresa`). NO se auto-crea la empresa ni el candidato hasta que la empresa exista en el Panel. (Dato real: solo 2 de 21 CIFs coinciden hoy → este caso será frecuente.)
3. **Nivel de estudios** (letra → código FUNDAE 1-10): **secuencial A=1, B=2, C=3, D=4, E=5, F=6, G=7, H=8, I=9, J=10**. El texto del nivel es equivalente al del Panel.
4. **Grupo profesional** (romano → categoría profesional 1-5): **I=1 (Directivo), II=2 (Mando intermedio), III=3 (Técnico), IV=4 (Cualificado), V=5 (Baja cualificación)**.
5. **Grupo cotización**: mapeo **directo quitando el cero** ('01'→1, '07'→7). Misma numeración TGSS 1-11.
6. **Candidato**: `nombre_contacto = firmante_nombre`, `email` y `telefono` del contrato. Tipo `empresa_organizadora`, vinculado a la empresa hallada por CIF.
7. **Campos extra del alumno** (cargo, curso_interes, horas, fecha_prevista_inicio, comentarios): **se conservan en staging como referencia** (no van a `alumnos`, pero ayudan a elegir acción formativa al crear el grupo).

## Estado a fecha de hoy (2026-07-09)

- ✅ **Ejecutado hoy:** import del snapshot de las 2 tablas (`contratos_encomienda` 21 filas, `encomienda_alumnos` 7 filas) a la BD **local** `webcourses2014` del contenedor `mi-proyecto-mysql-1` (como root, verbatim). Verificado (conteos + acentos OK). *Esto fue solo para inspeccionar estructura; NO es la integración.*
- ⏳ **Pendiente (esta integración):** todo lo descrito abajo — sin implementar aún. Se retoma mañana.
- ✅ **PDF resuelto (2026-07-09):** el **endpoint `descargar-contrato.php` YA está desplegado** en el servidor externo (`https://www.webcurso.es/encomienda/descargar-contrato.php`, Apache/PHP 5.6.25) y el **token ya existe**. Verificado: sin token responde `403 "Acceso denegado"` (protección activa). → El **auto-descargar del PDF se implementa ACTIVO** (ya no es diferido). El token va **solo en `ENCOMIENDA_PDF_TOKEN` del `.env`** (nunca en el repo/plan).
- 🆕 **El sistema externo evolucionó (2026-07-09):** nuevo flujo de **ratificación** + estado "Pendiente ratificación", **panel del cliente** (edita/borra alumnos, elige curso Active), y el endpoint resuelve el **PDF vigente** desde la BD. Ajustes incorporados al plan en §2, §4 (punto 4) y §7. **Pendiente confirmar con el dev:** que el SQL `sql_encomienda_ratificacion.sql` esté ejecutado en producción.

## Arquitectura de la solución

Flujo: **[Remoto] contrato firmado → [Cron sync] → [Panel] mirror de contrato + Candidato (si hay empresa) + Alumnos en staging → [Admin crea Grupo] → materializa Alumno + matricula.**

Se reutiliza el patrón existente de staging (`alumnos_legacy_pool`), el patrón de conexión legacy on-the-fly (`ImportLegacyData.php`), el trait `LegacyMappings`, y el patrón de selección "alumnos fidelizados" de `MatriculacionPanel`.

### 1. Conexión remota (config)

- Definir conexión `encomienda` **on-the-fly** dentro del comando (patrón de `app/Console/Commands/ImportLegacyData.php:34-47`, que ya construye una conexión completa con host propio), usando **nuevas variables de entorno**:
  - `.env` / `.env.example`: `ENCOMIENDA_DB_HOST`, `ENCOMIENDA_DB_PORT` (3306), `ENCOMIENDA_DB_DATABASE` (`webcourses2014`), `ENCOMIENDA_DB_USERNAME`, `ENCOMIENDA_DB_PASSWORD`.
- El comando valida las 4 credenciales y hace `DB::connection('encomienda')->getPdo()` antes de leer; aborta con mensaje claro si no conecta.
- **Prerrequisito operativo (fuera de código)**: el servidor remoto `130.117.90.142` debe aceptar conexiones MySQL desde el servidor del Panel (usuario **de solo lectura** recomendado, puerto 3306 accesible o túnel SSH). La usuaria debe rellenar las credenciales reales en `.env`.

### 2. Tablas locales del Panel (staging + mirror)

Dos migraciones nuevas en `database/migrations/`:

**`encomienda_contratos`** (espejo + estado de procesamiento):
- `source_id` (unsignedInteger, **unique** — id remoto de `contratos_encomienda`), `referencia_aceptacion`.
- Datos empresa/firmante: `empresa_cif` (normalizado, indexado), `empresa_razon_social`, `empresa_domicilio`, `empresa_localidad`, `firmante_nombre`, `firmante_nif`, `firmante_cargo`, `email`, `telefono`, `saldo_fundae`, `tiene_rlt`, `origen_externo` (**VARCHAR flexible**, no enum: `online`/`admin_pdf`/`PDF`/`-digital-anulado`…), `estado_externo` (**VARCHAR flexible**: `recibido`/`Pendiente ratificación`/`saldo_calculado`/`confirmado`…), `pdf_path`, `pdf_hash`, `aceptado_en`.
- Lado Panel: `empresa_id` (nullable, FK), `candidato_id` (nullable, FK), `estado_procesamiento` enum(`pendiente_empresa`,`candidato_creado`,`error`) default `pendiente_empresa`, `error_message` (nullable), `sincronizado_en`.
- **⚠️ Importante (novedad 2026-07-09):** el sistema externo añadió el estado **"Pendiente ratificación"** y conceptos de origen `PDF`/`-digital-anulado`. Por eso `origen_externo` y `estado_externo` se guardan como **VARCHAR**, NO como enum rígido — así el sync no se rompe si aparecen valores nuevos. En cada re-sync se refrescan (incluido `pdf_path`/`pdf_hash`, que cambian si hubo ratificación).

**`encomienda_alumnos_staging`** (espeja campos de `alumnos` + referencia):
- `source_id` (unsignedInteger, **unique** — id remoto de `encomienda_alumnos`), `encomienda_contrato_id` (FK local), `contrato_source_id`.
- Campos que van a `alumnos`: `nombre`, `apellido1`, `apellido2`, `nif` (normalizado), `email`, `telefono`, `niss`, `grupo_cotizacion_tgss`, `fecha_nacimiento`, `sexo`, `nivel_estudios` (int mapeado), `categoria_profesional` (int mapeado).
- Referencia (no van a `alumnos`): `cargo`, `curso_interes`, `horas`, `fecha_prevista_inicio`, `comentarios`.
- Lado Panel: `alumno_id` (nullable, FK — se rellena al materializar), `estado` enum(`pendiente`,`materializado`,`descartado`) default `pendiente`, `sincronizado_en`.

Modelos nuevos: `app/Models/EncomiendaContrato.php` y `app/Models/EncomiendaAlumnoStaging.php` con sus relaciones (`EncomiendaContrato hasMany EncomiendaAlumnoStaging`, `belongsTo Empresa/Candidato`; staging `belongsTo Alumno`).

### 3. Mapeos de código (extender `LegacyMappings`)

Añadir al trait `app/Console/Commands/Concerns/LegacyMappings.php` (hoy NO existen mapeos letra→estudios ni romano→categoría):
- `mapearNivelEstudiosLetra(?string $letra): ?int` — `A`..`J` → 1..10 (`ord(strtoupper) - 64`, validando rango 1-10).
- `mapearCategoriaProfesionalRomano(?string $romano): ?int` — tabla `['I'=>1,'II'=>2,'III'=>3,'IV'=>4,'V'=>5]`.
- `mapearGrupoCotizacionEncomienda(?string $n): ?string` — `ltrim($n,'0')` validado 1-11.
- Reutilizar tal cual: `normalizarCif()`, `normalizarNif()`, `separarApellidos()`, `normalizarTextoMapeo()`.
- Fechas del alumno remoto vienen en formatos sucios (`21/01/1981`, `21011981`, `2101981`, `09/11/1983`): añadir/reutilizar un `normalizarFechaFlexible()` (existe lógica similar en `PdfFichaInscripcionParser`); si no es trivial reutilizar, implementar un helper local tolerante (dd/mm/yyyy, dd-mm-yyyy, con guardas). Si no parsea → `null` (campo nullable).

### 4. Comando de sincronización

`app/Console/Commands/SincronizarEncomienda.php` → signature `encomienda:sincronizar {--dry-run} {--force}`. Usa el trait `LegacyMappings`. Patrón idempotente de `ImportarAlumnosBonificados.php`.

Lógica por cada contrato remoto (leído de la conexión `encomienda`, ordenado por `id`):
1. UPSERT del mirror en `encomienda_contratos` por `source_id` (idempotente; sin `--force` no re-procesa contratos ya en estado `candidato_creado`).
2. Normalizar CIF → `Empresa::where('cif', $cifNorm)->first()`.
   - **Si NO existe** → `estado_procesamiento='pendiente_empresa'`, sin candidato. (Se re-evaluará en la próxima corrida.) Igualmente se hace UPSERT de sus alumnos en staging (para no perderlos).
   - **Si existe** → si el contrato no tiene `candidato_id`, crear Candidato: `TipoCandidato::where('codigo','empresa_organizadora')`, `Candidato::create([...firmante_nombre/email/telefono, empresa_id, estatus 'pendiente'])`, luego `$candidato->inicializarRequisitos()` (método de dominio en `Candidato.php:259`). Guardar `candidato_id` + `empresa_id` en el mirror, `estado_procesamiento='candidato_creado'`.
3. Por cada alumno remoto del contrato: UPSERT en `encomienda_alumnos_staging` por `source_id`, aplicando los mapeos (estudios letra→int, categoría romano→int, cotización directo), `separarApellidos(apellidos)`, `nombre = nombre_completo`, normalización NIF/fecha/niss. No se toca `alumno_id`/`estado` si ya estaba materializado.
4. **Manejo de ediciones y borrados del cliente (novedad 2026-07-09):** el cliente ahora puede **editar y borrar** sus alumnos desde su propio panel externo tras enviarlos. Por tanto:
   - **Edición:** el UPSERT por `source_id` ya refresca los datos de las filas staging aún en `estado='pendiente'` (no materializadas). Las ya `materializado` NO se pisan automáticamente (el Alumno real ya vive en el Panel); si cambian en origen, se reporta en el resumen para revisión manual.
   - **Borrado:** si una fila que teníamos en staging desaparece del origen (no vino en esta corrida) y sigue en `estado='pendiente'`, se marca `estado='descartado'` (deja de ofrecerse en la UI). Si ya estaba `materializado`, se deja intacta y se anota en el resumen.
5. Resumen final con contadores (contratos nuevos, candidatos creados, pendientes_empresa, alumnos staged, editados, descartados, errores) al estilo `ImportarAlumnosBonificados` (líneas 150-170). `--dry-run` no escribe.

Cron en `routes/console.php` (patrón existente): `Schedule::command('encomienda:sincronizar')->everyThirtyMinutes()->timezone('Europe/Madrid')->onFailure(fn () => \Log::error(...))` — **cadencia confirmada: cada 30 minutos**.

**Botón manual "Sincronizar ahora"** (confirmado): además del cron, la vista de contratos de encomienda (sección 6) expone un botón que invoca el mismo comando en caliente (`Artisan::call('encomienda:sincronizar')`) y muestra el resumen al admin — mismo patrón que el botón "Reejecutar enriquecimiento" de `participantes-bonificados-index.blade.php`. Útil cuando el admin sabe que acaba de entrar un contrato y no quiere esperar al ciclo de 30 min.

### 4b. Contrato de encomienda firmado (PDF) — enlace al alumno

**Corrección de enfoque (confirmada con la usuaria):** el contrato de encomienda NO va como requisito del candidato, sino **a nivel del alumno dentro del grupo**, reutilizando el mecanismo ya existente "Subir Ficha/Encomienda PDF" → pivot `grupo_formativo_alumno.ficha_inscripcion_path` (`ficha_inscripcion_tipo='encomienda'`, icono 📎). Como un contrato puede tener varios alumnos, el mismo PDF se enlaza a cada alumno de ese contrato al materializarlo.

**Restricción de acceso descubierta:** los PDFs firmados viven en el filesystem del servidor externo bajo una carpeta protegida. Se verificó que `https://www.webcurso.es/encomienda/contratos_firmados_encomienda/CE-*.pdf` devuelve **HTTP 403** (protegido, correcto por GDPR) incluso con user-agent de navegador. La BD solo guarda la **ruta** (`pdf_path`) y el `pdf_hash`, NO el binario. La usuaria no tiene SFTP. → **El auto-fetch del PDF por HTTP directo/SFTP NO es viable.**

**Solución confirmada — Opción A: endpoint PHP con token** (se puede modificar el código PHP del sistema externo). Se descartan LONGBLOB (infla BD + duplica almacenamiento) y `LOAD_FILE`/SFTP (no viables). Los PDFs se quedan en disco; el Panel los descarga autenticado.

> **Estado 2026-07-09 — endpoint DESPLEGADO y verificado. Auto-descarga ACTIVA.** El archivo `descargar-contrato.php` ya está subido a `https://www.webcurso.es/encomienda/descargar-contrato.php` con su token, y responde `403 "Acceso denegado"` sin token (protección OK). Se implementa el auto-descargar del PDF como parte del build. El token se guarda **solo en `.env`** (`ENCOMIENDA_PDF_TOKEN`), nunca en el repo. El código sigue **gated por config** (si las 2 variables están vacías → subida manual), pero aquí ya se rellenan → queda operativo.
>
> **Mejora del endpoint (la hizo el desarrollador):** el endpoint **resuelve el PDF vigente desde la BD** — si el contrato tuvo **ratificación**, entrega el `-ratificado.pdf` y no el `-digital-anulado`. Por tanto el Panel **solo pasa `ref` + `token`** y confía en recibir siempre el PDF correcto; no calcula rutas ni versiones. Verificado por el dev: token OK→PDF, token mal→403, traversal→400, ref inexistente→404 (coincide con la especificación).
>
> **Matiz del `pdf_hash`:** tras una ratificación el PDF cambia y su SHA-256 se recalcula. La verificación de integridad en el Panel debe comparar contra el `pdf_hash` **recién sincronizado** del mirror (que el sync refresca en cada corrida), no contra uno viejo. Si no coinciden por desfase de sync, se guarda el PDF igualmente y se anota (no se bloquea la materialización).

**En el servidor externo (webcurso.es) — 1 archivo nuevo `descargar-contrato.php`** (fuera de este repo):
- Recibe `?ref=CE-...&token=...`.
- Valida el token contra un secreto compartido con `hash_equals()` (anti-timing).
- **Sanea `ref` con regex estricto** `^CE-\d{8}-[a-f0-9]{6}$` → previene path traversal.
- Sirve **solo** desde `contratos_firmados_encomienda/` con `Content-Type: application/pdf` + `readfile()`. Solo HTTPS.
- **El token es único y compartido** (no uno por PDF): identifica al Panel como sistema autorizado. La distinción de qué PDF se pide va en el parámetro `ref`.

Ejemplo del endpoint (plantilla a subir al servidor externo):

```php
<?php
// descargar-contrato.php  →  colocar en /encomienda/
const TOKEN_SECRETO = 'PEGA_AQUI_TU_TOKEN';                       // openssl rand -hex 32 ; mismo valor que ENCOMIENDA_PDF_TOKEN en el Panel
const CARPETA_PDFS  = __DIR__ . '/contratos_firmados_encomienda';

if (!hash_equals(TOKEN_SECRETO, $_GET['token'] ?? '')) { http_response_code(403); exit('Acceso denegado'); }

$ref = $_GET['ref'] ?? '';
if (!preg_match('/^CE-\d{8}-[a-f0-9]{6}$/', $ref)) { http_response_code(400); exit('Referencia no valida'); }

$ruta = CARPETA_PDFS . '/' . $ref . '.pdf';
if (!is_file($ruta)) { http_response_code(404); exit('Contrato no encontrado'); }

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: attachment; filename="' . $ref . '.pdf"');
readfile($ruta);
```

**En el Panel:**
- Config nueva: `ENCOMIENDA_PDF_BASE_URL` = `https://www.webcurso.es/encomienda/descargar-contrato.php` (confirmada) y `ENCOMIENDA_PDF_TOKEN` = **el token real, solo en `.env`** (`.env.example` lo lleva vacío; nunca el valor real en el repo).
- El código de auto-descarga se escribe **condicionado a la config (gated)** por robustez: `materializarAlumnoEncomienda()` solo intenta descargar si ambas variables están rellenas. Como ahora SÍ lo están → **queda operativo**. (Si algún día se vacían, cae a subida manual sin romperse.)
- Cuando está activo: `Http::timeout(...)->get(base_url, ['ref'=>referencia, 'token'=>secreto])`; verificar que la respuesta es PDF y (opcional pero recomendado) que su **sha256 coincide con `pdf_hash`** del mirror; guardar con el mismo mecanismo de la subida manual → `storage/app/private/fichas-inscripcion/{grupo_id}/{nif}_{timestamp}.pdf`, setear pivot `ficha_inscripcion_path`, `ficha_inscripcion_tipo='encomienda'`, `ficha_inscripcion_subida_en`.
- **Fallback:** si la descarga falla (o está desactivada), el alumno se materializa igual y el admin sube el PDF a mano (mecanismo existente). La UI (Sección 5) muestra la referencia (`CE-...`) como pista.

### 5. UI — selección de alumnos de encomienda en el Grupo Formativo

En `app/Livewire/Webcurso/MatriculacionPanel.php` + su vista:
- En `render()` (junto a la query de fidelizados, líneas 1477-1532), añadir colección `$alumnosEncomienda`: filas de `encomienda_alumnos_staging` con `estado='pendiente'` cuyo contrato tenga `empresa_id === $this->candidato->empresa_id`.
- Nueva **"Sección 5: Alumnos desde encomienda"** en `resources/views/livewire/webcurso/matriculacion-panel.blade.php` (tras la sección fidelizados, línea ~794), replicando el patrón de tabla+fila clicable de fidelizados (checkbox ✓, badges), mostrando además `curso_interes`/`horas` como referencia.
- Nuevo método `materializarAlumnoEncomienda(int $stagingId)`:
  1. Cargar la fila staging; `Alumno::updateOrCreate(['nif'=>$nif,'empresa_id'=>$empresaId], [...campos mapeados...])` (misma clave que el resto del panel).
  2. Aplicar las **mismas validaciones** que `agregarAlumnosAlGrupo` (líneas 860-922): grupo abierto, no autónomo, `tieneGrupoActivoEnPeriodo`, `puedeAceptarEnTramo` (tope 80).
  3. `$grupo->alumnos()->syncWithoutDetaching([$alumno->id])` + recalcular `descripcion_fundae`.
  4. Marcar staging `estado='materializado'`, `alumno_id=$alumno->id`.
- (Opcional, mismo patrón) botón "descartar" que marca la fila staging como `descartado`.

### 6. Visibilidad de contratos pendientes de empresa

Como la usuaria eligió "dejar pendiente", hace falta una superficie para verlos y actuar. Añadir una vista Livewire ligera `app/Livewire/Webcurso/EncomiendaContratosIndex.php` + ruta `/webcurso/encomienda`: listado de `encomienda_contratos` con su estado, resaltando los `pendiente_empresa` (con CIF + razón social del contrato para que el admin dé de alta la empresa y re-lance el sync). Enlazable desde el menú lateral.

Incluye el **botón "Sincronizar ahora"** (método `sincronizarAhora()` que hace `Artisan::call('encomienda:sincronizar')` y muestra el resumen del output) — complemento manual del cron de 30 min. *(La vista completa puede fasearse, pero el botón manual + el sync son parte del núcleo junto con la materialización.)*

### 7. Novedades del sistema externo (actualización 2026-07-09)

El desarrollador del sistema externo amplió la funcionalidad. Impacto en esta integración (ya reflejado arriba):

1. **Flujo de ratificación + estado "Pendiente ratificación"** (Recibido → Pendiente ratificación → Saldo calculado → Confirmado). Cuando la firma no la hizo el representante legal declarado, se pide ratificar; el PDF digital queda como `-digital-anulado` y el ratificado (`-ratificado.pdf`) pasa a ser el vigente. → Mirror guarda `estado_externo`/`origen_externo` como VARCHAR flexible (§2); el endpoint entrega siempre el PDF vigente (§4b).
2. **Panel del cliente**: el cliente puede **ver/editar/eliminar sus alumnos** y elegir curso (solo cursos `Active` de `tbl_courses`, validado en servidor). → El sync maneja ediciones/borrados (§4, punto 4); el `curso_interes` que guardamos como referencia es ahora un curso real y fiable.
3. **Endpoint `descargar-contrato.php`**: desplegado, con token, resuelve el PDF vigente desde la BD y compatible con PHP 5.6 (§4b).

**Prerrequisito operativo a confirmar con el desarrollador:** que el script `sql_encomienda_ratificacion.sql` (el `ALTER` del estado) **esté ejecutado en la BD de producción**. El endpoint básico ya responde, pero la resolución del PDF ratificado depende de esa columna/estado nuevo. Confirmar también que la carpeta `encomienda/` completa (6 PHP modificados + endpoint + PDF nuevo) esté subida.

## Archivos a crear / modificar

**Crear:**
- `database/migrations/*_create_encomienda_contratos_table.php`
- `database/migrations/*_create_encomienda_alumnos_staging_table.php`
- `app/Models/EncomiendaContrato.php`, `app/Models/EncomiendaAlumnoStaging.php`
- `app/Console/Commands/SincronizarEncomienda.php`
- `app/Livewire/Webcurso/EncomiendaContratosIndex.php` + vista (sección 6, opcional fase 2)
- **[✅ HECHO · en el servidor externo, fuera del repo]** `descargar-contrato.php` — endpoint PHP con token, ya desplegado y verificado (ver §4b). No requiere más acción salvo poner el token en el `.env` del Panel.

**Modificar:**
- `app/Console/Commands/Concerns/LegacyMappings.php` (3 métodos de mapeo nuevos + fecha flexible)
- `app/Livewire/Webcurso/MatriculacionPanel.php` (query `$alumnosEncomienda` + `materializarAlumnoEncomienda`)
- `resources/views/livewire/webcurso/matriculacion-panel.blade.php` (Sección 5)
- `routes/console.php` (cron)
- `.env` / `.env.example` (vars `ENCOMIENDA_DB_*` + `ENCOMIENDA_PDF_BASE_URL` + `ENCOMIENDA_PDF_TOKEN`)
- Menú lateral `resources/views/components/sidebar-layout.blade.php` (enlace, si se hace la vista)

## Verificación (end-to-end)

1. **Migraciones**: `./vendor/bin/sail artisan migrate` crea las dos tablas.
2. **Sync en dry-run** contra la BD local `webcourses2014` (que ya tiene los datos importados) apuntando `ENCOMIENDA_DB_*` al propio contenedor mysql: `./vendor/bin/sail artisan encomienda:sincronizar --dry-run` → debe reportar 21 contratos, ~2 candidatos creables (CIFs que matchean: B98969645, B10749364), ~19 `pendiente_empresa`, 7 alumnos staged. Verifica los mapeos en el resumen.
3. **Sync real**: quitar `--dry-run`; comprobar en BD: `encomienda_contratos` (21 filas, estados correctos), `candidatos` (2 nuevos con requisitos inicializados vía `requisitos_candidato`), `encomienda_alumnos_staging` (7 filas con `nivel_estudios`/`categoria_profesional`/`grupo_cotizacion_tgss` mapeados: p.ej. estudios `F`→6, grupo_prof `IV`→4, cotización `07`→7).
4. **Idempotencia**: re-ejecutar el comando → 0 candidatos nuevos, 0 duplicados en staging.
5. **UI**: abrir un Candidato con empresa que tenga alumnos en staging, entrar a Matriculación, crear un Grupo Formativo, y en la Sección 5 seleccionar un alumno de encomienda → verifica que se crea el `Alumno` real, se adjunta al grupo (pivot `grupo_formativo_alumno`), la fila staging pasa a `materializado`, y aplican las validaciones (solapamiento / tope 80).
6. **Caso pendiente**: dar de alta manualmente una empresa cuyo CIF estaba pendiente, re-lanzar sync → su contrato pasa a `candidato_creado` y se crea el candidato.
7. `./vendor/bin/sail php artisan test` para no romper nada existente.

## Fuera de alcance / notas

- **Regla innegociable de correos**: la creación de candidatos NO debe disparar emails a destinatarios reales en `APP_ENV != production` (el sync solo crea registros; no envía recordatorios — esos los maneja el cron de recordatorios existente, que ya respeta Mailpit en dev).
- El único cambio en el sistema PHP externo es añadir `descargar-contrato.php` (endpoint con token, §4b). El resto es solo lectura remota (BD).
- La materialización de alumnos reutiliza las validaciones existentes; no se duplica lógica de matriculación Moodle.
