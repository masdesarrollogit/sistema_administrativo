# Estado actual del Panel

Snapshot del estado de desarrollo. Ultima actualizacion: 2026-05-24.

## Modulos completados

### Gestion de candidatos
- **CandidatosIndex**: listado paginado (15/pagina), busqueda por nombre/email/telefono/razon_social de empresa, filtros por tipo y estatus. Oculta desactivados y cancelados por defecto. Boton eliminar candidato (elimina requisitos y archivos adjuntos asociados). **Icono de telefono clicable** junto al nombre del candidato (enlace `tel:` para integracion con Momena u otra app VoIP)
- **CandidatosIndex — columna Alta + filtros de fecha (2026-05-20)**: nueva columna **Alta** entre Estatus y Acciones que muestra la fecha de creacion del candidato (`created_at`) con icono de calendario indigo, formato `dd/mm/YYYY` y `diffForHumans()` corto debajo (ej. "hace 3 d"). Tooltip nativo con la hora completa `dd/mm/YYYY HH:mm`. Segunda fila de filtros con dos `<input type="date">` (**Alta desde** / **Alta hasta**) aplicados como `whereDate('created_at', ...)` — ignoran la hora, los extremos se incluyen. Selector adicional **Orden** (Mas recientes / Mas antiguos primero) que conmuta entre `latest()` y `oldest()`. Las tres nuevas propiedades (`$filtroDesde`, `$filtroHasta`, `$orden`) persisten via `queryString` y resetean paginacion en sus hooks `updating*()`. Boton "Limpiar filtros" extendido para detectar tambien rango y orden activos. Mismo patron de UI que [AlumnosIndex](../../app/Livewire/Webcurso/AlumnosIndex.php) (filtros desde/hasta) y misma convencion visual de fecha que [participantes-bonificados-index.blade.php:289-292](../../resources/views/livewire/webcurso/participantes-bonificados-index.blade.php#L289-L292)
- **Candidato model**: accessor `telefono_e164` — normaliza el telefono a formato E.164 (`+34XXXXXXXXX`) aceptando cualquier variante guardada (`971435090`, `0034916590303`, `+34915489870`, etc.). Elimina espacios, guiones y puntos antes de normalizar
- **CandidatoForm**: crear/editar candidato. Autocompletado de empresa segun tipo. Auto-crea empresa si no existe (firstOrCreate). **Campo "nombre de curso" eliminado** — ya no existe en el formulario
- **CandidatoEstatus**: gestion detallada — requisitos, archivos adjuntos, configuracion de recordatorios, pausar/reactivar/desactivar. Boton **"Completar todos"** para marcar todos los requisitos pendientes de una vez
- Estados del candidato: `pendiente`, `completo`, `cancelado`, `pausado`, `desactivado`

### Sistema de requisitos
- Inicializacion automatica al crear candidato segun TipoCandidato
- 3 tipos de candidato: `empresa_organizadora`, `empresa_externa`, `particular`
- 3 estados de requisito: `pendiente` → `en_proceso` → `completado`
- Documentos adjuntos por requisito (documento_path)
- Al completar todos los obligatorios → candidato pasa a "completo" automaticamente

### Recordatorios automaticos
- Cron diario 09:00 Europe/Madrid (comando: `candidatos:enviar-recordatorios`)
- Frecuencia configurable por candidato (dias entre envios, por defecto 7)
- Maximo de recordatorios configurable (por defecto 5)
- Pausado automatico al alcanzar el limite
- Email personalizado con adjuntos del candidato y de los tipos de requisito
- BCC a email de administracion
- Soporte `--dry-run` para previsualizar sin enviar
- **Nota:** el campo "observacion" es interno y NO se envia en los correos
- Resumen diario a las 13:00 para administracion (comando: `candidatos:enviar-resumen`)

### Importacion de datos
- **Empresas**: CSV delimitado por punto y coma. Logica UPSERT por CIF (actualiza si existe, crea si no)
- **Grupos**: CSV delimitado por punto y coma. Trunca tabla antes de importar. Excluye denominaciones "NO VA". Extrae CIF de la denominacion
- **Participantes bonificados**: XLS/XLSX con PhpSpreadsheet. Trunca tabla antes de importar
- Soporte para ano actual y anterior (tablas `*_anterior`)
- Soporte dual CSV/XLS para empresas, grupos, participantes y acciones formativas
- Deteccion automatica de fechas seriales de Excel

### Dashboard
- Estadisticas de empresas: total, PYMEs, credito asignado/dispuesto/disponible, promedio
- Estadisticas de grupos: total, con CIF, empresas sin grupos
- Toggle entre ano actual y anterior
- Ultima actualizacion visible

### Empresas
- **EmpresasIndex**: listado paginado (12/pagina) con filtros (CIF, razon social, PYME, nueva creacion, bloqueada). Ordenacion por columnas
- **EmpresasSinGrupos**: empresas sin grupos formativos. Muestra porcentaje del universo
- Modal de detalle de empresa (trait HasEmpresaModal compartido)
- Email de saldo disponible

### Participantes bonificados
- **ParticipantesBonificadosIndex**: listado paginado (25/pagina) con filtros (nombre, NIF, CIF, estado, grupo)
- Modal de empresa con envio de email de saldo (CC a administracion@webcurso.es y prospectos@webcurso.es)
- Estadisticas: total participantes, CIFs unicos

### Autenticacion
- Laravel Breeze con roles Spatie Permission

### Importacion legacy
- Comando `webcurso:import-legacy` para migrar datos desde base de datos webcourses2014
- Soporte para tablas historicas con flag `--anterior`

### Importacion de alumnos bonificados (desarrollado 2026-04-07, refactor 2026-05-03)
- Comando `alumnos:importar-bonificados` — cruza `participantes_bonificados` (FUNDAE) con la tabla local `alumnos_legacy_pool` (snapshot del legacy) para crear alumnos completos
- Cruce por NIF: `participantes_bonificados.nif_participante` ↔ `alumnos_legacy_pool.nif`
- **Fallback por nombre+empresa** cuando NIF FUNDAE no coincide con NIF legacy (caso real: Juan Sanchez `74848858G` FUNDAE vs `44818858J` legacy)
- **Alumno mínimo + flag `datos_pendientes=true`** cuando un NIF no se encuentra en pool ni por nombre — quedan visibles para que admin complete manualmente
- Datos del pool: nombre, apellidos, email, telefono, fecha nacimiento, NISS, nivel estudios, categoria profesional, grupo cotizacion
- Mapeos automaticos en trait `LegacyMappings`: `level_of_studies` (texto) → codigo FUNDAE 1-10, `professional_category` → 1-5, `listed_group` → grupo cotizacion 1-11
- Separacion automatica de apellidos (`last_name` → `apellido1` + `apellido2`), limpieza de sufijos legacy como "(REPASO)"
- Busqueda de `empresa_id` por CIF del participante bonificado
- Flags: `--dry-run` (preview sin insertar), `--force` (actualizar existentes)
- Idempotente: UPSERT por NIF+empresa_id; alumnos sin email ni datos pendientes se completan al re-ejecutar
- **Auto-ejecución**: tras subir XLS de participantes en `/webcurso/importar-csv` se invoca el comando automáticamente y muestra resumen al usuario

### Migración masiva legacy → pool local (desarrollado 2026-05-03)
- Comando `alumnos:migrar-legacy` — snapshot one-shot de `webcourses2014.tbl_member` a la nueva tabla local `alumnos_legacy_pool` (3,030 NIFs únicos válidos)
- **Fase A (pool)**: por cada NIF persona válido en legacy (formato `^[0-9]{8}[A-Z]$` o NIE), UPSERT en pool con datos personales + `legacy_nid`, `legacy_company_text`, `legacy_cif_resuelto`
- **Fase B (alumnos directos)**: para los NIFs cuyo CIF empresa se puede resolver, crear alumno directo en `alumnos`. Resolución multi-fuente del CIF:
  - **a)** `tbl_member.nid` ↔ `empresas.cif` (registros recientes — caso Juan, ~284 alumnos)
  - **b)** `tbl_member.company` con formato CIF ↔ `empresas.cif` (registros antiguos — caso Xavier Vidal)
  - **c)** Fuzzy match `tbl_member.company` ↔ `empresas.razon_social` normalizado (~5 alumnos)
- **Anti-duplicados**: detecta homónimos (mismo nombre+apellido1+empresa, NIF distinto) y enriquece el alumno existente en lugar de crear nuevo
- Flags: `--dry-run`, `--force`, `--solo-pool`, `--solo-alumnos`, `--sin-fuzzy-razon-social`, `--limit=N`
- Resultado típico: 3,030 entradas en pool, ~289 alumnos nuevos creados, ~2,694 NIFs en pool sin empresa derivable (quedan disponibles cuando aparezca su CIF en futuros XLS FUNDAE)
- **Conexión legacy se usa solo en este comando** — el flujo regular ya no consulta webcourses2014 en vivo
- Conexion legacy: reutiliza credenciales MySQL principales apuntando a DB `webcourses2014`

### Vista Participantes Bonificados — banner + filtros (mejorado 2026-05-03)
- Banner amarillo arriba del listado cuando hay participantes sin email registrado: muestra conteo y botón "Reejecutar enriquecimiento" que invoca `alumnos:importar-bonificados --force`
- Badge "📧 sin email" en cada fila sin email; badge "⚠ datos pendientes" en filas cuyo alumno tenga `datos_pendientes=true`
- Filtro nuevo `filtroSinEmail` (toggle) para listar solo participantes sin email registrado en alumnos

### Historial de cursos legacy (desarrollado 2026-05-03)
- Comando: la **Fase D** del comando `alumnos:migrar-legacy` (o `--solo-cursos`) replica `webcourses2014.tbl_member_courses` JOIN `tbl_member` JOIN `tbl_courses` a la tabla local `alumnos_legacy_cursos`
- Resultado: **4,092 entradas** procesadas, **2,994 NIFs únicos** con historial
- Modelo `AlumnoLegacyCurso` con relación inversa `Alumno::cursosLegacy()` (HasMany por NIF, igual patrón que `participantesBonificados`)
- Campos importados: nif, course_id, curso_titulo, curso_short_name, curso_horas, fecha_inicio, fecha_fin, estado_curso (Running/Completed/Closed/Upcoming), resultado (Pass/Fail/Not Declared), formation_group_alpha, formation_group_number, legacy_company_text, legacy_cif_resuelto, source_mc_id, source_mem_id
- En el modal Historial del alumno aparece como **"LEGACY · Historial webcourses2014"** con columnas: Curso, Acción formativa, Grupo, Empresa, Fechas (sin Estado ni Resultado — esos pertenecen al modelo legacy)
- Badge "N legacy" violeta en la columna Grupos del listado de alumnos
- Filtro Tipo: opción "Con historial legacy" para listar solo alumnos con cursos del legacy

### Enriquecimiento acción/grupo cursos legacy (desarrollado 2026-05-03)
- Comando `alumnos:enriquecer-cursos-legacy` — rellena `formation_group_alpha`, `formation_group_number` y `grupo_id_fundae` para cursos legacy que migraron sin esa info
- **Estrategia 1 — vía tabla `grupos`**: JOIN por `empresa.cif`, fechas (inicio/fin) y nombre del alumno en `denominacion`. Toma `codigo_grupo_accion_formativa` (acción), `codigo_grupo` (grupo) y `grupo_id`. Resultado: ~87 enriquecidos
- **Estrategia 2 — vía `participantes_bonificados`**: cruce por NIF + fechas exactas, parsea `id_codigo_grupo` con regex `/(N) accion/grupo/`. Resultado: ~20 enriquecidos
- Campo `origen_enriquecimiento` registra la fuente (`grupos_fundae` o `participantes_bonificados`) — visible en la UI como leyenda gris
- Auto-ejecución: el comando se invoca automáticamente como **Fase E** dentro de `alumnos:migrar-legacy` (tras Fase D)
- Flags: `--dry-run`, `--force` (sobrescribe acción/grupo existente)
- En el modal Historial cada fila muestra el formato `(grupo_id) acción/grupo` (ej: `(75143) 201/1`) cuando los datos están disponibles, igual al formato de FUNDAE
- Cuando `formation_group_alpha` matchea con `acciones_formativas.numero_accion` del Panel, se muestra el nombre completo de la acción (ej: "Contabilidad total 60h a")

### Auditoría y rescate manual de cursos legacy (desarrollado 2026-05-24)
- Comando `alumnos:auditar-enriquecimiento-legacy` — **read-only**, clasifica los cursos legacy sin enriquecer en 6 escalones según qué tan cerca están del matching automático:
  - Escalón 1: matchearía con la lógica actual (CIF+fi+ff+LIKE nombre completo)
  - Escalón 2: falla solo el LIKE → matchea con primer nombre (rescatable alta confianza)
  - Escalón 3: falla LIKE + fecha_fin (caso típico de bug fecha en legacy)
  - Escalón 4: solo CIF+fecha_inicio coincide, candidato único (rescatable confianza media)
  - Escalón 5: solo CIF+fecha_inicio, múltiples candidatos (ambiguo)
  - Escalón 6: sin candidato en `grupos` (falta importar el grupo FUNDAE correspondiente)
- Flags: `--year=YYYY` (filtra por año tocado), `--csv=path` (exporta CSV)
- Con `--year` exporta TODOS los cursos sin acción del año (incluso sin alumno vinculado y escalones 4-6) con columna `tiene_alumno_vinculado` para revisión manual
- CSV en `storage/audit/legacy-{año}-sin-accion.csv` con candidato sugerido por escalón
- **No modifica datos**. Pensado para revisar antes de aplicar rescates manuales puntuales con UPDATE directo

### Convención `origen_enriquecimiento='no_bonificado'` (desarrollado 2026-05-24)
- Valor nuevo del campo `alumnos_legacy_cursos.origen_enriquecimiento` para marcar cursos que NO deben enriquecerse con acción/grupo FUNDAE porque corresponden a alumnos **autónomos**, **privados**, **repaso** u otras categorías no bonificadas
- Convención: cuando un curso legacy es revisado y se confirma que no aplica matching FUNDAE, se le pone `origen_enriquecimiento='no_bonificado'` (en lugar de dejarlo en NULL pendiente)
- Beneficio: distingue "pendiente real (NULL)" de "revisado y descartado (no_bonificado)"; deja de aparecer en futuras corridas del comando de auditoría como pendiente
- Otros valores existentes del campo: `'grupos_fundae'`, `'participantes_bonificados'` (cron automático), `'manual_escalon_2'`, `'manual_escalon_4'` (rescates manuales con confianza alta/media)
- **Estado 2026 al cierre 2026-05-24**: de 61 cursos legacy con fechas 2026 originalmente sin acción → 23 rescatados con UPDATE manual (`manual_escalon_2` y `manual_escalon_4`), 38 marcados como `no_bonificado`, 0 pendientes

### AlumnosIndex — rediseño columna "Curso del año" + filtros simplificados (mejorado 2026-05-24)
- **Columnas eliminadas**: "Grupos" (badges de conteo por fuente) y "Estado" (toggle activo/inactivo). El método `toggleActivo()` también se eliminó del componente
- **Columna nueva "Curso {año}"**: muestra el curso más reciente del alumno que toca el año filtrado, unificando las 4 fuentes (`gruposFormativos`, `participantesBonificados`, `matriculasAutonomas`, `cursosLegacy`)
  - Método privado `resolverCursoReciente(Alumno $a, int $year): ?array` en el componente Livewire que consulta las 4 relaciones filtradas por año, mapea cada una a un array con `tipo`, `denominacion`, `accion`, `grupo`, `fecha_inicio`, `fecha_fin`, y devuelve el más reciente + conteo de otros (`otros_2026`)
  - UI: denominación + badge de tipo + píldora indigo `acción/grupo` + fechas + botón `+N más` que abre el modal Historial
  - Badges de tipo: `FUNDAE` azul, `FUNDAE imp.` esmeralda, `Autónomo` ámbar, `Legacy` violeta, `No bonificado` naranja claro con borde
- **Filtro Año por defecto = año actual**: `mount()` setea `$filtroAno = (string) date('Y')` si está vacío. `limpiarFiltros()` también lo preserva al año actual
- **Filtro de año mejorado**: usaba `whereYear('fecha_inicio')`, ahora usa rango `fecha_inicio <= 'YYYY-12-31' AND fecha_fin >= 'YYYY-01-01'` para capturar cursos que cruzan años (ej. 2025-12 → 2026-01)
- **Alumnos sin curso en el año filtrado se ocultan** (whereHas OR entre las 4 fuentes)
- **Filtros Tipo simplificados** (mutuamente excluyentes):
  - `bonificado`: alumnos con `gruposFormativos` + `participantesBonificados` + `cursosLegacy` NO no_bonificado en el año, **excluyendo** alumnos con ALGÚN curso legacy `no_bonificado` en el año
  - `autonomo`: con matrículas autónomas
  - `no_bonificado`: con al menos un curso legacy marcado `origen_enriquecimiento='no_bonificado'`
- Se eliminaron las opciones "Con grupos FUNDAE" y "Con historial legacy" del select (englobadas en "Bonificado")

### Modal Historial — agrupación por año con destacado del año en curso (mejorado 2026-05-24)
- Las 4 secciones del modal (Grupos FUNDAE Panel, Participación FUNDAE importada, Matrículas autónomas, Historial webcourses2014) ahora agrupan los cursos por año (`fecha_inicio?->year`)
- El bloque del año actual (`date('Y')`) aparece **primero**, destacado visualmente:
  - Borde izquierdo `border-l-4 border-emerald-500`, fondo `bg-emerald-50/40`
  - Header con punto pulsante verde + texto "EN CURSO" + contador de cursos
- Los bloques de años pasados aparecen debajo en orden descendente:
  - Borde izquierdo gris fino, fondo gris muy claro, opacidad 90% en la tabla
- Backend: `render()` aplica `groupBy(fn ($r) => $r->fecha_inicio?->year ?? 0)->sortKeysDesc()` a las 4 colecciones; pasa `$anoActual = (int) date('Y')` a la vista

### Reportes Moodle — Reportes 1 al 7 (módulo completo, desarrollado 2026-05-06 / 14)

Dashboard `/webcurso/reportes-moodle` con **KPIs clicables** que abren cada Reporte. Solo procesa alumnos cuyo grupo `estado=en_curso` y `fecha_inicio<=hoy<=fecha_fin` (año 2026).

**Vocabulario (importante para no confundirse):**
- **Fase** = etapa de desarrollo (no es atributo del alumno)
- **Reporte** = unidad funcional/producto de negocio (detección + tabla + notificaciones + log)
- **Badge** = clasificación visual interna dentro de un Reporte
- **Filtro** = control de UI que recorta el contenido de un Reporte (tutor, empresa, búsqueda)

**Reportes activos (entregados):**
- **Reporte 1 — No conectados** (Fase 1). Engloba dos badges visuales internos:
  - 🟡 Nunca entró al sitio (`lastaccess_global = 0`)
  - 🟠 No entró al curso (`lastaccess_global > 0` pero `lastaccess_curso = 0`)
- **Reporte 2 — Inactivos** (Fase 2). Badge 🟣 indigo.
  - **Estado**: `lastaccess_curso > 0` AND `dias_inactivo > 3`. Independiente de aprobación.
  - **Email de rescate al alumno**: solo si `inactivo=true` AND `aprobado=false` (no insistir a aprobados).
  - **Aprobado** = `nota_total >= 50` AND `cuestionario_final_realizado`.
- **Reporte 3 — Riesgo crítico** (Fase 3). Badge 🔴 rojo.
  - **Estado**: entró al curso AND `nota_total < 50` AND `pct_tiempo_transcurrido >= 50%`.
  - **Excluye** alumnos que nunca entraron al curso (ellos son R1, no R3).
  - **Email de rescate al alumno**: semanal (throttle 168h), sin tope. Solo a no aprobados (implícito porque nota<50).
  - **Excluido si pre_cierre**: si el alumno está en R4, R3 NO le envía email (R4 manda).
  - **Listado en email del tutor**: sección propia con tabla roja; prioridad sobre Inactivo en el bucket del tutor.
- **Reporte 4 — Pre-cierre** (Fase 4). Badge 🟧 ámbar oscuro.
  - **Estado**: últimas 72h antes de `fecha_fin` AND `cuestionario_final_realizado = false` (independiente de nota).
  - **Email de rescate al alumno**: diario (throttle 23h para evitar doble envío en el mismo día), sin tope.
  - **Prioridad MÁXIMA**: si un alumno está en R4 y también cumple R1, R2, R3 o R5, **SOLO** recibe el email de R4. Los otros crones lo excluyen explícitamente con `where('pre_cierre', false)`.
  - **Listado en email del tutor**: primera sección (más urgente), columna "Horas restantes" y "Cuestionario pendiente".
  - **Aprobado=false** sigue aplicando (si ya aprobó no recibe).
- **Reporte 5 — Apto sin examen** (Fase 5). Badge 🟡 amarillo claro (positivo).
  - **Estado**: `nota_total >= 50` AND `cuestionario_final_realizado = false`. Complementario a R3 (nota<50).
  - **Email al alumno**: tono POSITIVO ("ya alcanzaste 50 pts, solo falta el cuestionario"). Semanal (throttle 168h), sin tope.
  - **Excluido si pre_cierre**: si está en R4, R5 NO envía.
  - **Listado en email del tutor**: sección al final con tono positivo (color amarillo, columna nota destacada).
  - **Tabla en UI**: badge primario de R5 si no está también en R1; sub-badge "+Apto sin examen" si está combinado con R1.
- **Reporte 6 — Apto / Finalizado con éxito** (Fase 6). Badge 🟢 verde (estado terminal positivo).
  - **Estado**: `aprobado = true` (nota>=50 AND cuestionario_final_realizado). Usa el flag `aprobado` que ya existía en el snapshot, no hace falta columna nueva.
  - **Email al alumno**: ÚNICA vez de FELICITACIÓN al detectar la aprobación. El comando verifica que no exista log previo de `TIPO_ALUMNO_APTO` para ese (alumno, grupo) y solo envía una vez.
  - **Listado en email del tutor**: sección verde "Aprobados — éxitos" al final del email del lunes.
  - **Sin alertas**: no genera urgencia, solo cierre/confirmación.
- **Reporte 7 — No aptos / Reinicios** (Fase 7, post-cierre). Badge 🟥 rojo oscuro.
  - **Detección**: nota_total < 50 AND cuestionario_final_realizado = false AND fecha_fin del grupo < hoy. Persistente en tabla nueva `alumno_no_aptos`.
  - **Arquitectura SEPARADA del snapshot**: el snapshot diario solo procesa cursos activos. Cuando un curso termina, un cron separado (`detectar-no-aptos`, diario 01:30) examina el último snapshot disponible del alumno, y si cumple condiciones, crea entry permanente en `alumno_no_aptos`. La detección requiere que el snapshotter haya procesado el grupo al menos UNA vez antes de su finalización.
  - **Ofrecimiento de REINICIO al alumno**: email semanal (throttle 7d) hasta 4 ofrecimientos máximos en ventana de 30 días desde detección. El email contiene un botón `mailto:` al admin con asunto y body predefinidos. Cero infraestructura web — el alumno simplemente envía email al admin pulsando el botón.
  - **Cierre del ciclo**: el admin tiene en el dashboard del Reporte 7 un botón "✓ Marcar reiniciado" que detiene los emails y registra fecha + user. También "✗ Rechazar" para descartar.
  - **Estados del ciclo**: `pendiente` → `ofrecido` (tras 1er email) → `aceptado` / `reiniciado` / `caducado` / `rechazado`.
  - **Sin tope global de reinicios por alumno**: `max_reinicios_alumno = 0` (ilimitado). Configurable.
  - **Tabla `alumno_reinicio_ofrecimientos`** registra cada email enviado para auditoría.
  - **NO entra en el email semanal del tutor** (R7 es responsabilidad del admin, no del tutor — el curso ya terminó).
  - **UI dedicada**: cuando filtro=`no_aptos`, la tabla se sustituye por una vista con columnas específicas (fin curso, nota final, # ofrecimientos, estado, acciones).
- **R5 — Apto sin examen (amarillo claro)**: `nota_total >= 50` AND `cuestionario_final_realizado = false`. Continuo durante el curso, recordatorio de cerrar con el cuestionario final.
- **R6 — Apto / Finalizado con éxito (verde)**: `aprobado = true`. Continuo desde que se cumple la condición.
- **R7 — No aptos (rojo oscuro)**: `nota_total < 50` y por consecuencia sin cuestionario final. **Solo aparece después de `fecha_fin`** del curso (es un veredicto, no una alerta).

**Solapamiento intencional**: un mismo alumno puede aparecer en varios Reportes simultáneamente (ej. Inactivo + Riesgo crítico). Cada Reporte tiene su propio mensaje y cron.

**Reporte 7 — Reinicios (implementado)**: el email al alumno No apto le ofrece reiniciar el curso. El admin recibe la solicitud por `mailto:`. Tope máximo de ofrecimientos por alumno **configurable** (default 4 en 30 días). Cierre manual desde el dashboard del Reporte 7 con botón **"✓ Marcar reiniciado"** o **"✗ Rechazar"**. Auditoría en tabla `alumno_reinicio_ofrecimientos`. El registro del alumno No apto vive en `alumno_no_aptos` (estado permanente post-cierre).

**Nuevas tablas:**
- `alumno_progreso_moodle` — snapshot diario por (pivot, fecha): lastaccess, progress, nota_total, dias_inactivo, inactivo, **aprobado**, **cuestionario_final_realizado**, **riesgo_critico**, **pre_cierre**, **apto_sin_examen**, **pct_tiempo_transcurrido**, etc.
- `alumno_no_aptos` — registro PERMANENTE de alumnos suspendidos (R7). Una fila por (alumno, grupo). Incluye estado del ciclo de reinicio, fecha de detección, audit del cierre.
- `alumno_reinicio_ofrecimientos` — auditoría de emails de ofrecimiento de reinicio (1, 2, 3, 4 por alumno_no_apto).
- `alumno_notificaciones_log` — auditoría de envíos (alumno_id O tutor_id, tipo, fase, payload, exitoso, error_message)
- `alumno_calificaciones_moodle` — detalle de notas por actividad por snapshot, con flags `is_course_total` y `is_final_quiz`

**Servicios:**
- `MoodleReportingService` — orquesta llamadas batch a Moodle: `getLastAccessGlobalBatch`, `getUserCourseStats`, `getUserGrades` (parsea `gradereport_user_get_grade_items`)
- `AlumnoProgresoSnapshotter` — ejecuta el snapshot diario, calcula inactividad, limpia rows obsoletas

**Comandos artisan + schedules (Europe/Madrid):**
- `reportes-moodle:snapshot` — diario 02:00 — pobla snapshots
- `reportes-moodle:notificar-no-conectados` — diario 10:00 — Reporte 1, días [3, 6, 9] desde inicio, tope 3
- `reportes-moodle:notificar-inactivos` — diario 10:15 — Reporte 2, throttle 72h por alumno+grupo, sin tope
- `reportes-moodle:notificar-riesgo-critico` — diario 10:30 — Reporte 3, throttle 168h (semanal), sin tope. Excluye pre_cierre.
- `reportes-moodle:notificar-pre-cierre` — diario 10:45 — Reporte 4, throttle 23h (diario sin doble envío), sin tope. Prioridad máxima.
- `reportes-moodle:notificar-apto-sin-examen` — diario 11:00 — Reporte 5, throttle 168h (semanal), sin tope. Excluye pre_cierre.
- `reportes-moodle:notificar-apto` — diario 11:15 — Reporte 6, ÚNICO envío por (alumno, grupo). Verifica log previo `TIPO_ALUMNO_APTO`.
- `reportes-moodle:detectar-no-aptos` — diario 01:30 — Reporte 7, examina cursos finalizados y crea registros en `alumno_no_aptos` (idempotente).
- `reportes-moodle:notificar-no-aptos` — diario 11:30 — Reporte 7, email semanal al alumno con `mailto:` al admin. Hasta 4 ofrecimientos en 30 días.
- `reportes-moodle:notificar-tutores` — lunes 09:00 — email semanal con seis secciones (pre-cierre + no conectados + riesgo crítico + apto sin examen + inactivos + aprobados). R7 NO entra aquí.

**Mailables (mailer dedicado `moodle`, alias tutorias@webcurso.es, CC administracion@webcurso.es):**
- `AlumnoNoConectadoMail` — Reporte 1
- `AlumnoInactivoMail` — Reporte 2 con nota acumulada y días restantes
- `AlumnoRiesgoCriticoMail` — Reporte 3 con nota, % tiempo transcurrido y advertencia urgente
- `AlumnoPreCierreMail` — Reporte 4 con horas restantes, nota y mensaje "ÚLTIMO AVISO" (asunto y estilo ámbar oscuro)
- `AlumnoAptoSinExamenMail` — Reporte 5 con tono POSITIVO ("ya tienes los 50 puntos, solo falta cuestionario"). Asunto: "🎯 Ya tienes los 50 puntos...". Color amarillo claro.
- `AlumnoAptoMail` — Reporte 6 de FELICITACIÓN al aprobar. Asunto "🎉 ¡Has aprobado...". Color verde (#059669). Único envío.
- `AlumnoNoAptoMail` — Reporte 7 de OFRECIMIENTO de reinicio. Asunto "Una segunda oportunidad...". Color rojo oscuro (#7f1d1d). Botón mailto: al admin.
- `TutorReporteSemanalMail` — único email del lunes con las seis secciones (R4 + R1 + R3 + R5 + R2 + R6). R7 va por canal separado al admin.

**UI Livewire** (`ReportesMoodleIndex`) — dashboard híbrido:
- **10 KPIs clicables** (botones que abren cada Reporte): Total · No conectados · Inactivos · Riesgo crítico · Pre-cierre · Apto sin examen · Aprobados · No aptos · Conectados · % en riesgo
- **Selector "Reporte"** (dropdown) con 9 opciones: Todos / No conectados / Inactivos / Riesgo crítico / Pre-cierre / Apto sin examen / Aprobados / No aptos / Conectados
- **Vista alternativa cuando filtro=No aptos**: la tabla principal de snapshots se sustituye por una tabla dedicada (`alumno_no_aptos`) con columnas específicas: fin curso, nota final, # ofrecimientos, estado del reinicio, acciones (botones "✓ Marcar reiniciado" / "✗ Rechazar")
- **Leyenda de badges** explicando los sub-estados visuales internos
- Filtros: search, reporte, tutor, empresa, accion. URL queryString.
- Tabla 10 columnas con badge de estado + sub-etiqueta "ÚLTIMO AL CURSO: ..." + nota auxiliar cuando aplica
- Columna "Total curso" con barra de progreso color-coded + botón "Ver notas" → modal con 40+ items por alumno
- Modal Historial de notificaciones (clicable desde la columna Avisos)
- Botón único "↻ Refrescar todos ahora" en el header (sin botón por fila)
- **Switch global "Notificaciones automáticas" (ON/OFF)** en el header — kill switch operativo persistido en BD (tabla singleton `reportes_moodle_settings`, modelo `ReportesMoodleSettings`). Con flag OFF, los **7 comandos de notificación a alumno + el de tutores** retornan SUCCESS sin enviar tras un `warn("⏸ DESACTIVADAS")`. El snapshot, `detectar-no-aptos` y los `--dry-run` siguen corriendo igual. Banner amarillo persistente cuando está OFF, leyenda "Modificado por X · dd/mm HH:mm" debajo del toggle.
- Las KPIs hacen `wire:click="abrirReporte(...)"` y la tabla cambia su contenido en vivo

**Switch de notificaciones — detalle técnico:**
- Tabla `reportes_moodle_settings` (singleton, `id=1`): `notificaciones_activas` (bool), `updated_by` (FK users nullable), `timestamps`. Migración: [`2026_05_13_000002_create_reportes_moodle_settings_table.php`](../../database/migrations/2026_05_13_000002_create_reportes_moodle_settings_table.php).
- Default en producción: `true`. Default en `APP_ENV != production`: `false` (alineado con la regla "no enviar correos a destinatarios reales en dev/staging").
- Helpers del modelo: `ReportesMoodleSettings::actual()`, `::notificacionesActivas()`, `->cambiarEstado($activo, $userId)`.
- Chequeo en cada comando tras `--dry-run`: `if (!$dryRun && !ReportesMoodleSettings::notificacionesActivas()) { return self::SUCCESS; }` — patrón equivalente al de [`EnviarEmailSaldoBonificados.php:34`](../../app/Console/Commands/EnviarEmailSaldoBonificados.php).
- Toggle UI: `wire:click="toggleNotificaciones"` con `wire:confirm`. Auditoría visible (autor + timestamp Europe/Madrid). Sin permission check Spatie por ahora.

**Pre-requisito de Moodle:** 20 funciones webservice habilitadas en el token `paneldesarrollo` — las 14 originales + `core_completion_get_course_completion_status`, `core_enrol_get_enrolled_users`, `gradereport_user_get_grade_items`, `mod_quiz_get_user_attempts`, `core_course_get_categories`. Verificar con `core_webservice_get_site_info`.

**Documentación detallada:** [`docs/reportes-moodle.md`](../../docs/reportes-moodle.md)

### AlumnosIndex — filtros por fechas (desarrollado 2026-05-03)
- Tres filtros nuevos en `/webcurso/alumnos`:
  - **Año del curso** (dropdown con años disponibles 2018-2026, generado dinámicamente)
  - **Desde** (date picker — filtra por fecha_inicio >= valor)
  - **Hasta** (date picker — filtra por fecha_inicio <= valor)
- Los filtros buscan en las **4 fuentes** simultáneamente: `gruposFormativos`, `participantesBonificados`, `matriculasAutonomas`, `cursosLegacy` (via `whereHas` con OR)
- Combinables: año + desde + hasta se aplican como AND a cada subquery
- Persisten en URL (queryString) y se reinician con "Limpiar filtros"
- Implementación con eager generación de `aniosDisponibles` mediante UNION de `YEAR(fecha_inicio)` de las 4 tablas

---

### Tutores (desarrollado 2026-03-15)
- **TutoresIndex**: CRUD con filtros (nombre, tipo, activo). Modal crear/editar
- Muestra alumnos por tramo (T1/T2) con limite de 80
- tramo_horario pertenece al GrupoFormativo, NO al tutor
- Campo `moodle_username` (string, nullable) — nombre de usuario del tutor en Moodle, usado para autodetectar el aula al matricular
  - Ejemplos actuales: `tutoralvarop` (Alvaro), `tutorwebcurso@gmail.com` (David), `traquelg` (Raquel)

### Acciones Formativas FUNDAE (desarrollado 2026-03-15, actualizado 2026-04-06)
- **AccionesFormativasIndex**: listado paginado con estadisticas de vinculacion Moodle
- Filtros: denominacion, estado, area profesional, plataforma (m/a)
- Importacion XLS desde FUNDAE (UPSERT por numero_accion): si una accion ya existe se actualizan todos sus campos al reimportar
- Vinculacion con cursos reales de Moodle: autocomplete busca cursos via API Moodle (`core_course_search_courses`)
- Tipos de vinculacion: `activa`, `plantilla`, `repaso`, `desactualizado` (se elimino el tipo `tutor` — el tutor pertenece al grupo, no a la accion)
- La tabla pivot `accion_formativa_moodle_curso` NO tiene tutor_id ni idnumber_moodle (simplificada)
- Metodo `eliminarAccion(id)` disponible en el componente (pendiente: boton en la vista)

### Generacion XML FUNDAE (desarrollado 2026-03-15, corregido 2026-04-06)
- **FundaeXmlService**: genera 3 tipos de XML (Acciones Formativas, Inicio Grupo, Finalizacion Grupo)
- Esquemas XSD y ejemplos en `storage/fundae/`
- **Fix descripcion grupo (t_cadena100)**: `GrupoFormativo::getDescripcionFundaeAttribute()` trunca a 100 chars para cumplir el tipo XSD
- **Fix denominacion_limpia**: `AccionFormativa::denominacion_limpia` usaba regex que requeria letra de plataforma tras las horas — corregido para hacerla opcional, evitando "80h 80h" al generar la descripcion

### Integracion Moodle API (funcional desde 2026-03-21, actualizado 2026-03-26)
- **MoodleService**: completamente funcional. Ver `api-moodle.md` para referencia completa
- URL interna Docker: `http://moodle_app` con header `Host: localhost:8080`
- URL publica para alumnos: configurada en `MOODLE_PUBLIC_URL` (ej: `https://aula.1curso.com`)
- Token configurado en `.env` como `MOODLE_TOKEN`
- Patron de credenciales Moodle:
  - **Username**: email del alumno
  - **Password**: `ucfirst(nombre) + '4444*'` (ej: `Ana4444*`, `Carlos4444*`)
- Si el usuario ya existe en Moodle: se actualiza la contrasena y se rematricula
- Email de credenciales: mailer dedicado `moodle` (SMTP de `saldoswebcurso@gmail.com` con alias `tutorias@webcurso.es`), CC a `administracion@webcurso.es`
  - Incluye: usuario, contrasena, URL completa del curso (`/course/view.php?id=X`), fechas inicio/fin, parrafo de bonificacion
  - Remitente: `tutorias@webcurso.es` (configurado como alias "Enviar como" en Gmail de `saldoswebcurso@gmail.com`)
- **Timestamps en timezone Europe/Madrid**: fechas de inicio/fin se calculan en Madrid para que Moodle las muestre correctamente

### Matriculacion (desarrollado 2026-03-15, mejorado 2026-03-26)
- **GrupoFormativo**: entidad central. Vincula candidato, accion formativa, tutor, empresa, tramo, fechas
- **Alumnos**: asociados a empresa, reutilizables entre grupos (fidelizacion). NIF globalmente unico. Email obligatorio y globalmente unico
- **MatriculacionPanel**: componente Livewire anidado en CandidatoEstatus (cuando estatus=completo)

#### Flujo completo por plataforma:
**Plataforma Moodle (codigo 'm'):**
1. Crear grupo formativo (id_grupo_fundae se asigna automaticamente al crear). Campo **Dias** calcula fecha_fin automaticamente desde fecha_inicio
2. Agregar alumnos al grupo:
   - **Alumnos fidelizados**: lista de alumnos ya registrados en la empresa → boton "+ Añadir" (sin reingresar datos)
   - **Nuevo alumno individual**: formulario manual con validacion de NIF y email unicos
   - **Subida masiva**: importa la Ficha de Inscripcion Excel de WebCurso (cabecera en fila 10, columnas C/D/J/K/L/M/N/O/P/Q). Extrae: nombre, apellidos, telefono, email, fecha_nacimiento, NIF, NISS, grupo_cotizacion_tgss, nivel_estudios, categoria_profesional
3. Generar XML de Inicio de Grupo (solo activo cuando hay alumnos)
4. Subir XML a FUNDAE (manual)
5. Subir PDF de notificacion FUNDAE (solo activo cuando hay alumnos): valida que corresponde al grupo y marca como `comunicado`
6. Matricular en Moodle: autodetecta el aula → crea grupo Moodle → crea/actualiza usuarios → matricula con fechas → envia email credenciales
7. Grupo pasa a `en_curso` **solo cuando TODOS los alumnos** tienen `estado_moodle = matriculado`

**Plataforma Aulasystem (codigo 'a', www.plataformateleformacion.com):**
1-4. Igual que Moodle
5. Boton "Matriculado en Aulasystem" — marca todos con `estado_moodle='aulasystem'` → grupo pasa a `en_curso`

#### Reglas de negocio:
- Un alumno NO puede estar en dos grupos cuyas fechas se solapan
- Un grupo permanece abierto hasta 2 dias antes de la fecha de inicio
- Limite FUNDAE: 80 alumnos por tutor por tramo
- id_grupo_fundae se asigna automaticamente al crear el grupo
- Autodeteccion del aula Moodle: por moodle_username del tutor. Si hay varios cursos, selector manual
- El grupo solo pasa a `en_curso` cuando todos los alumnos estan matriculados (no parcialmente)
- Email de alumno: unico globalmente. Si ya existe en la BD se muestra error claro (no se permite duplicar)
- Alumnos fidelizados: al seleccionar un grupo, se muestran los alumnos de la empresa que aun no estan en ese grupo

#### Edicion:
- Grupos `abierto`: editar datos del grupo, eliminar, editar/quitar alumnos, agregar nuevos
- Grupos `comunicado` y `en_curso`: boton Gestionar disponible para agregar alumnos adicionales y editar datos de alumnos
- Campo **Dias**: al escribir el numero de dias y tener fecha_inicio, calcula fecha_fin automaticamente (`fecha_inicio + dias`)

#### Flujo de comunicacion a FUNDAE (secuencia obligatoria):
1. XML Inicio → activo solo cuando hay alumnos en el grupo
2. PDF FUNDAE → activo solo cuando hay alumnos (mismo requisito que XML)
3. Al validar el PDF → grupo pasa a `comunicado` automaticamente
4. No hay boton "Marcar comunicado" manual — el PDF es el unico camino (excepto grupos ya existentes en tabla importada)

#### Autocomplete de accion formativa:
- Muestra badge "Moodle" (azul) o "Aulasystem" (ambar) junto a cada resultado

#### Estados Moodle del alumno en pivot `grupo_formativo_alumno`:
- `pendiente` — no procesado
- `matriculado` — matriculado en Moodle
- `aulasystem` — matriculado en plataforma externa (marcado manualmente)
- `error` — fallo en el proceso

### Grupos formativos externos — bonificacion gestionada por la empresa cliente (desarrollado 2026-07-28)

Para candidatos de tipo `empresa_externa`: la empresa gestiona su propia bonificacion ante FUNDAE, comunica alli la accion y el grupo, y solo nos traslada el codigo resultante (ej. `241/3`). Nosotros impartimos el curso en Moodle, asi que el alumno debe quedar bajo el mismo seguimiento academico que un bonificado propio.

**Modelado**: es un `GrupoFormativo` con `gestion_externa = true`, NO una entidad nueva. Razon: el snapshot de Reportes Moodle recorre exclusivamente el pivot `grupo_formativo_alumno` y `alumno_progreso_moodle` tiene FK a ese pivot. Una entidad hermana (tipo `MatriculaAutonoma`) seria invisible para Reportes 1-7, que es justo el defecto que hoy tienen los autonomos 2x1.

**Columnas nuevas** (`grupos_formativos`): `gestion_externa` (bool, indexado) y `codigo_grupo_externo` (string 50, texto libre tal cual lo facilita la empresa).

**La empresa externa NUNCA entra en `empresas`** (corregido 2026-07-28): gestiona su propia bonificacion y nosotros no calculamos su credito, asi que figurar en `/webcurso/empresas` con saldo cero seria un dato inventado. Sus datos viven solo en `empresas_externas`, como registro de control.

En consecuencia:
- `grupos_formativos.empresa_id` y `alumnos.empresa_id` son NULL en los externos (ambas columnas son nullable)
- La razon social se copia a `alumnos.empresa_texto` (el mismo campo informativo que usan los particulares)
- `GrupoFormativo::$empresa_nombre` / `::$empresa_cif` resuelven el dato via `candidato->empresaExterna` cuando no hay FK
- `Candidato::razonSocialEntidad()` / `::cifEntidad()` dan el dato sin importar el tipo
- En el alta de candidato, el buscador de empresa por CIF **solo aparece en `empresa_organizadora`** (la unica que registramos porque le calculamos el saldo). En `empresa_externa` se teclean CIF y razon social, que se guardan con `EmpresaExterna::updateOrCreate` por CIF
- El panel de matriculacion lista los alumnos disponibles por candidato (grupos + matriculas) cuando no hay empresa registrada, en vez de por `empresa_id`

**Flujo** (sin paso `comunicado`): crear grupo con el checkbox "La bonificacion la gestiona la empresa cliente" (marcado y bloqueado si el candidato es `empresa_externa`) + campo unico Accion/Grupo → agregar alumnos (las 4 rutas habituales funcionan igual) → **Matricular en Moodle** → `en_curso`.

**Diferencias con un grupo propio**:
- No se asigna `id_grupo_fundae` (`asignarIdGrupoFundae()` hace `return` inmediato: no consume numeracion nuestra)
- Sin botones de ID FUNDAE, XML de inicio ni PDF de notificacion; `FundaeXmlService` lanza excepcion si un grupo externo entra en un lote de XML
- Fecha de inicio libre (permite retroactivo) y `estaAbierto()` exento de la regla de +2 dias; `scopeAbiertos()` aplica el mismo criterio
- El grupo de Moodle se llama con `codigo_grupo_moodle` (accessor: externo → `codigo_grupo_externo`, propio → `codigo_fundae`)

**Seguimiento academico**: identico al de los bonificados propios, sin tocar el modulo de Reportes. En cuanto el grupo pasa a `en_curso` con `moodle_course_id` y fechas vigentes, entra en el snapshot diario, en los Reportes 1-7, en los emails al alumno y en el resumen semanal al tutor.

**UI**: badge violeta `Externo` + pildora indigo con el codigo en el panel de matriculacion; badge `FUNDAE ext.` en la columna "Curso {año}" de AlumnosIndex (el filtro Tipo = `bonificado` los sigue incluyendo: son bonificados, solo que la bonifica otro).

**Tests**: [`tests/Feature/Webcurso/GrupoExternoTest.php`](../../tests/Feature/Webcurso/GrupoExternoTest.php) — 6 casos.

### Particulares — alumno que paga su curso (desarrollado 2026-07-28)

Para candidatos de tipo `particular`: el alumno paga el curso de su bolsillo. No hay bonificacion, asi que **no lleva grupo formativo** ni se comunica nada a FUNDAE. Se matricula individualmente en Moodle, igual que un autonomo 2x1.

**Modelado**: reutiliza `MatriculaAutonoma` con un campo `modalidad` nuevo:
- `autonomo_2x1` — curso gratis que acompana a un bonificado (comportamiento previo, valor por defecto)
- `particular` — el alumno paga; no pertenece a ninguna empresa cliente

La modalidad se deduce del tipo de candidato, sin selector manual. Constantes: `MatriculaAutonoma::MODALIDAD_AUTONOMO` / `::MODALIDAD_PARTICULAR`, scopes `autonomos()` / `particulares()`, helper `esParticular()`.

**Empresa como texto libre**: un particular no tiene empresa cliente. `alumnos.empresa_id` y `matriculas_autonomas.empresa_id` pasan a **nullable**, y se anade `alumnos.empresa_texto` (string 255) con la empresa que declara el alumno. Es **solo informativo**: no se crea ni se vincula nada en la tabla `empresas`. En AlumnosIndex, cuando no hay `empresa_id` se muestra `empresa_texto` con la leyenda "Particular".

**Fechas obligatorias y libres**: inicio y fin son requeridos (sin ventana no se pueden calcular R3 ni R4), pero la fecha de inicio no tiene restriccion: si el alumno paga hoy, puede empezar manana. El campo Dias calcula la fecha fin.

**UI**: la seccion "Grupos Formativos" se oculta por completo para candidatos particulares. La seccion de matriculas individuales cambia de titulo y color segun la modalidad ("Autonomos (oferta 2x1)" ambar / "Particulares (paga el alumno · sin bonificacion)" azul cielo). El listado de alumnos seleccionables de un particular sale de sus propias matriculas, no de una empresa.

**Email de credenciales**: sin parrafo de bonificacion FUNDAE (`esBonificado: false`), igual que los autonomos.

**Tests**: [`tests/Feature/Webcurso/MatriculaParticularTest.php`](../../tests/Feature/Webcurso/MatriculaParticularTest.php) — 6 casos.

### Reportes Moodle extendidos a matriculas individuales (desarrollado 2026-07-28)

Hasta ahora el snapshot solo recorria el pivot `grupo_formativo_alumno`, de modo que los autonomos 2x1 eran **invisibles** en todo el modulo de Reportes. Con la llegada de los particulares se generalizo: cualquier matricula en Moodle entra en el seguimiento academico, venga de un grupo formativo o de una matricula individual.

**Abstraccion — `App\Contracts\OrigenMatriculaMoodle`**: implementado por `GrupoFormativoAlumno` (bonificado) y `MatriculaAutonoma` (autonomo/particular). Normaliza lo que el snapshot y los avisos necesitan: `claveSnapshot()`, `claveOrigen()`, `alumnoIdMatricula()`, `moodleUserIdMatricula()`, `moodleCourseIdMatricula()`, `fechaInicioCurso()`, `fechaFinCurso()`, `tutorMatricula()`, `accionFormativaMatricula()`, `codigoGrupoMatricula()`, `tipoMatricula()`.

**Columnas nuevas**:
- `alumno_progreso_moodle`: `grupo_formativo_alumno_id` pasa a nullable + `matricula_autonoma_id` nullable + UNIQUE `(matricula_autonoma_id, fecha_snapshot)`
- `alumno_notificaciones_log`: `matricula_autonoma_id` nullable
- `alumno_no_aptos`: `grupo_formativo_id` nullable + `matricula_autonoma_id` nullable + `origen_clave` (`grupo:37` / `autonoma:12`). El UNIQUE pasa de `(alumno_id, grupo_formativo_id)` a `(alumno_id, origen_clave)` porque **un UNIQUE con columnas NULL deja de proteger en MySQL**

**Accessors en `AlumnoProgresoMoodle`** que sustituyen al antiguo `$snap->pivot->grupoFormativo`: `origen()`, `grupo`, `tutor_curso`, `accion_curso`, `fecha_inicio_curso`, `fecha_fin_curso`, `codigo_grupo`, `tipo_matricula`, `columnasOrigenLog()`. Los throttles de los crones usan el scope `AlumnoNotificacionLog::delOrigen($snap)` en lugar de filtrar por `grupo_formativo_id`.

**Mailables genericos**: los 7 mailables de alumno (`AlumnoInactivoMail`, `AlumnoNoConectadoMail`, `AlumnoRiesgoCriticoMail`, `AlumnoPreCierreMail`, `AlumnoAptoSinExamenMail`, `AlumnoAptoMail`, `AlumnoNoAptoMail`) reciben ahora `OrigenMatriculaMoodle $matricula` en lugar de `GrupoFormativo $grupo`.

**Requisito para entrar en el snapshot** (matriculas individuales): `estado = matriculado`, `moodle_user_id` y `moodle_course_id` poblados, y **ambas fechas** dentro de la ventana (`fecha_inicio <= hoy <= fecha_fin`, ano 2026). Sin fechas se omite: no se podrian calcular ni el % de tiempo transcurrido (R3) ni el pre-cierre (R4).

**En la UI de Reportes** la columna Accion/Grupo muestra el codigo FUNDAE en bonificados y una pildora `Particular` (azul cielo) / `Autonomo 2x1` (ambar) en las individuales, porque no hay grupo que mostrar.

### Autonomos 2x1 (desarrollado 2026-03-30, actualizado 2026-04-06)
- **MatriculaAutonoma**: entidad ligera para alumnos autonomos que no llevan grupo formativo FUNDAE
- Tabla `matriculas_autonomas`: candidato, alumno, accion_formativa, tutor, empresa, fechas, estado Moodle
- Seccion "Autonomos (2x1)" en MatriculacionPanel, hermana de "Grupos Formativos"
- Matricula individual en Moodle: crear/actualizar usuario → matricular → enviar email credenciales
- Fecha de inicio libre (sin restriccion FUNDAE de 2 dias antes)
- Seleccionar alumno existente de la empresa (solo los que no son bonificados) o crear nuevo inline
- Autodeteccion de aula Moodle por tutor (mismo patron que grupos)
- Estados: `pendiente`, `matriculado`, `error`
- **Edicion de alumno inline**: boton "Editar alumno" en cada tarjeta — abre formulario inline (nombre, apellidos, NIF, email, telefono) directamente bajo la tarjeta. Reutiliza los mismos metodos `abrirEditarAlumno`, `actualizarAlumno`, `cerrarEditarAlumno` del panel
- **Email sin bonificacion**: `CredencialesMoodleMail` se envia con `esBonificado: false` → el texto de bonificacion FUNDAE no aparece en el correo del autonomo
- **Eliminar matricula**: permitido en cualquier estado (pendiente, matriculado, error)

### Identificacion visual en AlumnosIndex — histórico (superado el 2026-05-24)
> **Esta sección quedó obsoleta tras el rediseño del 2026-05-24**. Las columnas "Grupos" y "Estado" se eliminaron; el filtro Tipo se simplificó (Bonificado / Autónomo / No bonificado). Ver secciones **"AlumnosIndex — rediseño columna 'Curso del año'"** y **"Modal Historial — agrupación por año"** más arriba para el comportamiento actual.

- Modelo `Alumno` mantiene las relaciones: `gruposFormativos()`, `matriculasAutonomas()`, `participantesBonificados()` (HasMany por NIF), `cursosLegacy()` (HasMany por NIF)
- `AlumnosIndex` mantiene los `withCount` (`grupos_total`, `grupos_activos`, `autonomos_total`, `bonificados_total`, `legacy_total`) — los usa el modal Historial

### Subida de Ficha/Encomienda PDF para alta de alumnos (desarrollado 2026-06-01)

Cuarta ruta de incorporación de alumnos al grupo formativo, alternativa al ingreso individual, alumnos fidelizados y subida masiva Excel. Sustituye el flujo manual de "abrir PDF → copiar/pegar campos al formulario" que era el cuello de botella diario del admin.

**Dos formatos soportados** (autodetectados por contenido y número de páginas):
- **Ficha de Inscripción** (1 página, datos del alumno en la única hoja)
- **Contrato de Encomienda WebCurso 2026** (3 páginas: p.1 empresa firmante, p.2 alumno, p.3 cláusulas)

**Pipeline multi-tier en `PdfFichaInscripcionParser`** (orquestador):
1. **Tier 1 — AcroForm**: para PDFs rellenados digitalmente. Extrae valores tanto del widget `/V` como de los XObject appearance streams. Cubre la mayoría de casos (Ejemplos 1, 2 y 5 de los fixtures).
2. **Tier 2 — Capa de texto**: para PDFs sin AcroForm pero con texto extraíble. Usa `extraerCamposTextoPlano()` con regex anclados por label.
3. **Tier 3 — OCR local**: para escaneos impresos (Ejemplo 3 tipo). Renderiza la página del alumno con Imagick (300 DPI, grayscale), aplica Tesseract con `lang=spa+eng` y PSM 6, luego usa `extraerCamposOcr()` con extracción line-based + patrones globales (NIF/email/fecha/NISS/CIF/teléfono). Reconstruye email cuando OCR confunde `@` por `G`/`E`/`C`. Guard: si no encuentra NIF NI email, cae a ilegible.
4. **Tier 4 — Ilegible**: manuscritos o PDFs corruptos (Ejemplo 4 tipo). Preview con campos vacíos para que el admin transcriba a mano. El PDF se archiva igualmente para custodia FUNDAE.

**Características de la UX**:
- Multi-PDF en una subida (hasta 20 archivos, 10 MB c/u).
- Drop-zone con `multiple` accept=application/pdf.
- Preview en tabla con una fila por PDF, totalmente editable (`wire:model.lazy`):
  - Badge tipo: `Ficha` (azul) / `Encomienda` (indigo) / `Ilegible · manual` (rojo)
  - Sub-badge `🔍 OCR` (naranja) cuando los datos vienen de Tier 3
  - Badge estado: `Crear` (verde) / `Actualizar` (ámbar, NIF existe con diff) / `Duplicado · ignorado` (gris, NIF repetido en la subida)
  - Campos amarillos = inferidos (ej. "Universitarios" → 7 ambiguo)
  - Campos rojos = obligatorios faltantes
  - Tooltips diff "antes: X → ahora: Y" en filas Actualizar
  - Avisos amarillos por fila: CIF no coincide con empresa del grupo, email reservado por otro alumno, etc.

**Normalizaciones aplicadas**:
- **Title Case** en `nombre`, `apellido1`, `apellido2`, `cargo` (PDFs en MAYÚSCULAS → "Greicy Lisbeth Barreto Colmenares")
- **Email** con normalización de artefactos: `*@` (AcroForm), `G@`/`E@`/`C@` (OCR), espacios entre local y dominio
- **NIF/CIF** uppercase + strip espacios/guiones
- **Fecha** parseo flexible dd/mm/yyyy, dd-mm-yyyy, dd.mm.yyyy
- **NISS** solo dígitos
- **Empresa razón social y curso**: NO se title-casean (preservar siglas tipo "S.L.", "NCS", "ChatGPT")

**Manejo robusto de casos reales detectados**:
- **Contaminación admin en Encomienda**: identifica el cluster admin (página 1) por anclas únicas en el pool de XObject IDs y lo descarta entero, preservando duplicados que correspondan al alumno (ej. razón social compartida).
- **Ediciones múltiples del PDF**: cuando el usuario edita un campo varias veces en un visor (Adobe Reader, navegador), el widget `/V` queda obsoleto en el primer valor mientras los XObject appearance streams acumulan todas las versiones con IDs altos. El parser ordena el pool DESC por ID para que el match patternable (email, NIF, fecha, etc.) coja la última versión visible. Para campos sin patrón (nombre/apellidos/cargo/curso), usa scoring greedy con orden ASC para mantener estabilidad.
- **Mapeos texto→código FUNDAE** para Estudios/Categoría profesional/Grupo cotización TGSS vía trait `LegacyMappings` extendido con patrones longest-first (evita matches de prefijo erróneos tipo "Trabajador" matcheando "Trabajador con baja cualificación" como "Trabajador cualificado").

**Validaciones**:
- Mime real (`mimetypes:application/pdf`, no solo extensión)
- CIF del PDF vs CIF de la empresa del grupo: warning si difiere, no bloquea
- NIF duplicado en la misma subida: marca como `duplicado · ignorado`
- Email duplicado entre alumnos distintos: bloquea fila, exige corrección
- Solapamiento de fechas y límite de tutor (80 alumnos por tramo): reutiliza la lógica existente de `agregarAlumnosAlGrupo`

**Persistencia del PDF original**:
- Carpeta: `storage/app/private/fichas-inscripcion/{grupo_id}/{nif}_{timestamp}.pdf`
- Pivot `grupo_formativo_alumno` con 3 campos nuevos: `ficha_inscripcion_path`, `ficha_inscripcion_tipo` (ficha|encomienda|manual), `ficha_inscripcion_subida_en`
- Modelo `GrupoFormativoAlumno` con observer `deleting` que borra el PDF al borrar el pivot (evita huérfanos)
- Descarga vía `descargarFichaPdf(grupoId, alumnoId)` con `Storage::disk('local')->download()`, no URL pública
- Icono **📎 PDF** clicable en la tabla del grupo (junto a Editar/Quitar)

**Por qué Tesseract y NO Claude Vision / Document AI** (decisión 2026-06-01):
- Coste $0/PDF vs $0.02-0.05/PDF (Claude) o $0.03/PDF (Document AI)
- 100% local → GDPR limpio (NIF/NISS/fechas no salen del Docker)
- Calidad 90-95% en impreso suficiente para el caso real WebCurso
- Reutiliza el parser regex existente (línea de código mínima en Tier 3)
- Manuscritos (Ejemplo 4) seguirían fallando con cualquier OCR, así que Claude Vision tampoco aportaría
- Setup mínimo: 2 paquetes APT (`tesseract-ocr`, `tesseract-ocr-spa`) + 1 composer package (`thiagoalessio/tesseract_ocr`)

---

## Inventario tecnico

| Aspecto | Cantidad |
|---|---|
| Modelos | 23 (+ AlumnoLegacyPool, AlumnoLegacyCurso, BonificadoEmailExclusion, AlumnoProgresoMoodle, AlumnoNotificacionLog, AlumnoCalificacionMoodle, GrupoFormativoAlumno) |
| Componentes Livewire | 14 (+ ReportesMoodleIndex) |
| Clases Mail | 13 (+ AlumnoNoConectadoMail, AlumnoInactivoMail, AlumnoRiesgoCriticoMail, AlumnoPreCierreMail, AlumnoAptoSinExamenMail, AlumnoAptoMail, AlumnoNoAptoMail, TutorReporteSemanalMail) |
| Comandos Artisan | 19 (+ los 8 de reportes-moodle: snapshot, notificar-no-conectados, notificar-inactivos, notificar-riesgo-critico, notificar-pre-cierre, notificar-apto-sin-examen, notificar-apto, detectar-no-aptos, notificar-no-aptos, notificar-tutores) — añadido `alumnos:auditar-enriquecimiento-legacy` el 2026-05-24 |
| Servicios | 5 (+ MoodleReportingService, AlumnoProgresoSnapshotter, PdfFichaInscripcionParser, PdfOcrExtractor) |
| Migraciones | 53 (+ gestion_externa en grupos_formativos, bonificacion_externa en empresas, empresa_texto en alumnos, modalidad en matriculas_autonomas, matricula_autonoma_id en las 3 tablas de reportes) |
| Contratos | 1 (OrigenMatriculaMoodle) |
| Configs de dominio | 5 (+ reportes_moodle.php, pdf_ocr.php) |

---

## Modulos pendientes de disenar y desarrollar

> **Reportes Moodle: COMPLETO** (Reportes 1-7, todas las fases entregadas 2026-05-06 a 2026-05-14). Ver [`docs/reportes-moodle.md`](../../docs/reportes-moodle.md).

1. **Integracion Zoho CRM** — sincronizacion de candidatos
2. **Facturacion** — Zoho Books (iniciar con mocks)
3. **Cierre de expediente FUNDAE** — generación de XML de finalización, comunicación al portal
4. **Reorganizacion de categorias Moodle** — Activos/tutor, Repasos, Desactualizados, Plantillas

---

## Decisiones de diseno resueltas

- GrupoFormativo es la entidad central (no Matricula): un grupo tiene accion, tutor, tramo, empresa, alumnos
- Tramo horario pertenece al grupo, no al tutor
- Un alumno no puede estar en dos grupos con fechas solapadas (validacion por rango, no por estado general)
- id_grupo_fundae secuencial por accion formativa, asignado automaticamente al crear el grupo
- Importacion masiva de alumnos: Ficha de Inscripcion Excel de WebCurso (formato fijo, cabecera fila 10)
- Notificacion al alumno: email con credenciales Moodle auto-generadas
- Patron credenciales Moodle: username=email del alumno, password=ucfirst(nombre)+'4444*'
- Email de credenciales: mailer dedicado `moodle` (Gmail con alias `tutorias@webcurso.es`), CC `administracion@webcurso.es`
- MoodleService: error "Message was not sent" de Moodle se maneja como warning sin interrumpir la matricula
- Timestamps Moodle: calculados en Europe/Madrid para que las fechas se muestren correctamente en Moodle
- Estado `en_curso`: solo cuando TODOS los alumnos del grupo tienen estado_moodle matriculado o aulasystem
- PDF FUNDAE como unico mecanismo para marcar grupo como `comunicado` (no hay boton manual)
- Alumnos de la empresa: visibles en tabla con checkboxes, funcion unica `agregarAlumnosAlGrupo` (eliminada la redundante `agregarAlumnoFidelizado`)
- Plataforma aulasystem (codigo 'a'): sin API disponible, matriculacion se marca manualmente desde el Panel
- Vinculacion AccionFormativa↔Moodle: sin tutor en el pivot (el tutor va en el grupo, no en la accion)
- Facturacion: iniciar con MockFacturacionService
- Campo "nombre de curso" eliminado del formulario de candidato (era redundante con la accion formativa del grupo)

- Autonomos (2x1): entidad separada MatriculaAutonoma, no usan GrupoFormativo. Matricula individual en Moodle sin FUNDAE
- Un alumno es bonificado (FUNDAE) O autonomo (2x1), nunca ambos. Validacion en: crearMatriculaAutonoma (server), agregarAlumnosAlGrupo (server), select de autonomos (filtrado en render)
- CredencialesMoodleMail: refactorizado para aceptar datos genericos (no depende de GrupoFormativo)
- Descripcion FUNDAE del grupo: truncada a 100 chars (limite XSD t_cadena100). Formato: `{CIF} {empresa} {alumno} {curso} {horas}h {tramo}{iniciales_tutor}`
- denominacion_limpia: regex corregido para eliminar horas con o sin codigo de plataforma al final (evita "80h 80h" en descripcion XML)

- Importacion de alumnos bonificados: el flujo regular consulta la tabla local `alumnos_legacy_pool` (no webcourses2014 en vivo). El comando one-shot `alumnos:migrar-legacy` mantiene esa tabla actualizada
- Resolución multi-fuente del CIF empresa en legacy (corrige nota previa errónea sobre `nid`): el campo "ID" del formulario antiguo guarda el CIF de empresa en `tbl_member.nid` (registros recientes, caso Juan) o en `tbl_member.company` con formato CIF (registros antiguos, caso Xavier). 146/189 empresas Panel (77%) tienen alumnos en legacy
- NIF persona en legacy y FUNDAE puede divergir para la misma persona: el comando de enriquecimiento tiene fallback por nombre+empresa para resolver estos casos sin crear duplicados
- Relacion Alumno→ParticipanteBonificado: HasMany por NIF (no FK), cruce de tablas por columna string. Sin indice en `nif_participante` (tabla pequena, rendimiento aceptable)
- AlumnosIndex historial: tres secciones independientes (grupos Panel, bonificados importados, autonomos) pueden coexistir. Antes eran mutuamente excluyentes (`@elseif`)
- Conexion legacy: no se usa usuario `webcourses2014` (no existe en MySQL). Se reutilizan credenciales principales (`sail/password`) apuntando a DB `webcourses2014`
- Historial cursos legacy: tabla `alumnos_legacy_cursos` desacoplada de `alumnos` (vinculación HasMany por NIF). Permite tener historial de NIFs que aún no tienen alumno en el Panel y se vinculan automáticamente cuando aparece el NIF en `alumnos`
- Enriquecimiento acción/grupo dos vías: tabla `grupos` (importada FUNDAE) primero (más confiable, 87 matches) y `participantes_bonificados` como fallback (parsea `(N) accion/grupo` del `id_codigo_grupo`, 20 matches). Cuando el legacy no tenía `formation_group_alpha/number` rellenados (~3,015 casos), estas dos fuentes recuperan la información via cruce por CIF + fechas + nombre alumno
- Modal Historial — sección legacy SIN columnas `Estado`/`Resultado`: esos valores (`Running`/`Completed`/`Pass`/`Fail`) son del modelo legacy y rompen la consistencia del modelo actual (estado_grupo: `comunicado`/`en_curso` y estado_moodle: `pendiente`/`matriculado`). Los campos siguen guardados en BD pero no se renderizan
- Filtros por fechas en AlumnosIndex: año + rango (desde/hasta) buscan en las 4 fuentes (gruposFormativos, participantesBonificados, matriculasAutonomas, cursosLegacy) via OR de `whereHas`. El dropdown de años se genera dinámicamente con UNION de `YEAR(fecha_inicio)` para reflejar siempre datos disponibles

- **Reportes Moodle**: snapshot diario (cron 02:00 Madrid) en lugar de queries en vivo — UI carga instantánea, permite detección día-a-día. Botón manual "Refrescar todos ahora" inline.
- **Reportes Moodle — definición de "alumno activo"**: `estado=en_curso` AND `fecha_inicio<=hoy<=fecha_fin`. El estado `en_curso` por sí solo no basta porque no se actualiza a `completado` automáticamente al pasar `fecha_fin`. Snapshotter limpia rows obsoletas en cada ejecución.
- **Reportes Moodle — vista única vs. seis**: una sola pantalla con filtros por color en lugar de seis vistas separadas (una por fase). Reduce navegación y los KPIs cobran sentido.
- **Reportes Moodle — tabla `alumno_notificaciones_log` separada**: NO se reutiliza `notificaciones_log` (atado a `candidato_id`). El nuevo log soporta `alumno_id` O `tutor_id` para registrar emails a ambos.
- **Reportes Moodle — diferencia `lastaccess_global` vs `lastaccess_curso`**: ambos vienen de la API de Moodle. Global=0 → "Nunca al sitio" (amarillo). Curso=0 con global>0 → "No entró al curso" (naranja). Curso>0 + >3d → "Inactivo" (indigo). Sub-etiqueta del badge SIEMPRE referida al curso para coherencia FUNDAE.
- **Reportes Moodle — tope de reenvíos al alumno**: Fase 1 con tope (default 3 emails los días 3/6/9 y luego escala al tutor). Fase 2 SIN tope (throttle 72h hasta que entre o termine el curso). Esta asimetría es intencional: en Fase 1 ya se reenvían credenciales (riesgo de spam); en Fase 2 son recordatorios con la nota actual.
- **Reportes Moodle — un solo email semanal al tutor** (`TutorReporteSemanalMail`): combina dos secciones (No conectados + Inactivos). Se eliminó el antiguo `TutorListadoNoConectadosMail` cuando se añadió Fase 2.
- **Reportes Moodle — captura de notas vía `gradereport_user_get_grade_items`** (JSON estructurado), NO `gradereport_user_get_grades_table` (HTML embebido). Requiere habilitar 4 funciones extra en el token de Moodle: `core_completion_get_course_completion_status`, `core_enrol_get_enrolled_users`, `gradereport_user_get_grade_items`, `mod_quiz_get_user_attempts`. Para exclusión de categorías Moodle también hace falta `core_course_get_categories` (5ª función, pendiente).

- **Reportes Moodle — vocabulario "Fase" vs "Reporte" vs "Filtro" vs "Badge"**: Fase = etapa de desarrollo interna; Reporte = unidad de producto/negocio (cada Fase entrega un Reporte completo); Filtro = control de UI que recorta DENTRO de un Reporte (tutor, empresa, búsqueda); Badge = sub-estado visual de un alumno dentro de un Reporte. Las claves del config respetan este vocabulario: `reporte_no_conectados`, `reporte_inactivos`, no `fase1`/`fase2`.
- **Reportes Moodle — "Inactivo" separa estado de acción**: el flag `inactivo=true` (visible en UI, badge indigo, KPI) se calcula con solo dos condiciones (entró al curso AND >3d sin entrar). El email de rescate (cron `notificar-inactivos`) AÑADE un filtro extra: solo `inactivo=true AND aprobado=false`. Razón: un alumno aprobado puede estar ausente legítimamente; sigue siendo inactivo pero no hay que insistirle por email.
- **Reportes Moodle — "Aprobado" FUNDAE**: `nota_total >= 50` AND `cuestionario_final_realizado`. Tener 50+ pts no basta; hay que haber rendido el cuestionario final también.
- **Reportes Moodle — exclusión categorías Moodle "Repaso" y "Foros"**: los cursos de esas categorías Moodle no entran en ningún Reporte. Configurable en `config/reportes_moodle.php`. Resolución dinámica de nombres a IDs vía `core_course_get_categories` (función pendiente de habilitar).
- **Reportes Moodle — dashboard híbrido con KPIs clicables**: la pantalla es a la vez resumen y selector. Las 5 KPIs son botones (`wire:click="abrirReporte(...)"`) que cambian la propiedad `$reporte` de Livewire y por tanto el filtro de la tabla. Se mantiene también el dropdown como fallback de teclado.

- **AlumnosIndex — vista enfocada al año en curso (2026-05-24)**: por defecto la tabla muestra solo alumnos con al menos un curso en el año actual (`date('Y')`). Sustituye al enfoque previo que listaba todos los alumnos siempre. Razón: el flujo diario de WebCurso es 99% del año en curso; los históricos se consultan via modal Historial o cambiando el filtro Año.
- **AlumnosIndex — columna unificada "Curso {año}" (2026-05-24)**: reemplaza las antiguas columnas "Grupos" (badges de conteo por fuente) y "Estado" (toggle activo/inactivo). Muestra el curso más reciente del año que unifica las 4 fuentes (gruposFormativos, participantesBonificados, matriculasAutonomas, cursosLegacy) con badge de tipo + píldora indigo acción/grupo + fechas + contador "+N más". Razón: la columna "Grupos" antigua tenía 4 badges con conteos sin contexto del curso real; la usuaria quería ver qué curso está haciendo cada alumno ahora.
- **`origen_enriquecimiento='no_bonificado'` (2026-05-24)**: convención nueva para `alumnos_legacy_cursos`. Marca cursos de alumnos autónomos / privados / repaso que NO deben matchearse con grupos FUNDAE. Distingue "pendiente real (NULL)" de "revisado y descartado". El badge en UI es naranja claro (`bg-orange-100 text-amber-700`).
- **Filtros Bonificado y No bonificado mutuamente excluyentes (2026-05-24)**: un alumno que tiene al menos un curso `no_bonificado` en el año filtrado SOLO aparece en "No bonificado", no en "Bonificado", aunque también tenga grupos FUNDAE. Razón: evita confusión visual cuando el `cursoReciente` sería el `no_bonificado` mientras el filtro dice "Bonificado".
- **Filtro de año por rango de fechas vs `whereYear` (2026-05-24)**: el filtro Año usa `fecha_inicio <= 'YYYY-12-31' AND fecha_fin >= 'YYYY-01-01'` en vez de `whereYear('fecha_inicio')`. Razón: captura cursos que cruzan años (ej. 2025-12 → 2026-01). El antiguo `whereYear` los perdía.
- **Modal Historial agrupado por año con destacado del año en curso (2026-05-24)**: las 4 secciones del modal (FUNDAE Panel / Bonificados / Autónomos / Legacy) agrupan cursos por año. Bloque del año actual con borde verde + fondo verde claro + texto "EN CURSO" arriba; años pasados en bloques grises atenuados abajo. Razón: cuando el alumno tiene cursos en varios años, antes se mezclaban indistinguibles en una tabla única.
- **Rescate manual de cursos legacy con tipos `manual_escalon_2` / `manual_escalon_4` (2026-05-24)**: cuando el cron automático no rescata por falla del LIKE de nombre, se aplica UPDATE manual marcando el origen para auditoría. Permite reversión y trazabilidad. Aplicado en 23 cursos del año 2026 tras revisión del CSV de auditoría.

- **Particular = modalidad de `MatriculaAutonoma`, no entidad nueva (2026-07-28)**: autonomos 2x1 y particulares comparten exactamente el mismo flujo (matricula individual en Moodle, sin grupo FUNDAE, sin XML ni PDF); la unica diferencia es que uno es gratis y el otro de pago, y que el particular no tiene empresa. Un campo `modalidad` los distingue sin duplicar el pipeline de matriculacion. La modalidad se deduce del tipo de candidato, no se elige a mano.
  - **Regla que se deriva**: compartir tabla obliga a que TODA consulta de negocio sobre autonomos filtre por modalidad (`->autonomos()` / `->particulares()`). Un `whereHas('matriculasAutonomas')` a secas mezcla ambos y es un bug. Ya corregidos: filtro Tipo de AlumnosIndex, `withCount` de autonomos (partido en `autonomos_total` + `particulares_total`) y la etiqueta de TutoresIndex. Cubierto por el test "el filtro Autónomos no devuelve particulares ni al revés".
- **Empresa del particular como texto libre (2026-07-28)**: se guarda en `alumnos.empresa_texto` y NO se crea fila en `empresas`. Razon: la tabla `empresas` es el universo FUNDAE (credito, plantilla, CIF); meter ahi el sitio donde trabaja un particular ensuciaria dashboard y estadisticas sin aportar nada, porque nunca se va a bonificar. Contrasta con el caso de las empresas externas, donde SI se crea espejo porque sus alumnos y grupos necesitan la FK.
- **Reportes Moodle desacoplados del grupo formativo (2026-07-28)**: en lugar de duplicar el snapshot para matriculas individuales, se introdujo el contrato `OrigenMatriculaMoodle` que ambos modelos implementan. Los 10 comandos y la UI preguntan al snapshot (`$snap->tutor_curso`, `$snap->fecha_fin_curso`, …) en vez de navegar `pivot->grupoFormativo`. Asi el modulo dejo de asumir que todo alumno con seguimiento viene de un grupo FUNDAE.
- **`origen_clave` en `alumno_no_aptos` (2026-07-28)**: con dos FK nullable, un UNIQUE `(alumno_id, grupo_formativo_id, matricula_autonoma_id)` no protegeria nada en MySQL (los NULL no colisionan) y `detectar-no-aptos` dejaria de ser idempotente. Se anade un discriminador de texto sin nulos (`grupo:37` / `autonoma:12`) y el UNIQUE pasa a `(alumno_id, origen_clave)`.
- **Grupo externo = flag en `GrupoFormativo`, no entidad nueva (2026-07-28)**: los grupos cuya bonificacion gestiona la empresa cliente viven en `grupos_formativos` con `gestion_externa = true`. Razon: el snapshot de Reportes Moodle recorre el pivot `grupo_formativo_alumno` y `alumno_progreso_moodle` tiene FK a ese pivot, sin `grupo_formativo_id`. Una entidad hermana no seria vista por el snapshot, los 10 comandos `reportes-moodle:*` ni el dashboard — exactamente lo que le pasa hoy a `MatriculaAutonoma`. Con el flag heredamos todo el seguimiento academico sin tocar el modulo de Reportes.
- **Empresa externa fuera de `empresas` (decidido 2026-07-28, tras revertir el espejo)**: se probo primero a crear una fila espejo en `empresas` por CIF para poder colgar de ella alumnos y grupos. Se descarto: `empresas` es el universo FUNDAE nuestro (credito, plantilla, cotizacion) y una externa aparecia ahi con saldo cero, un dato que nosotros no hemos calculado y que ensucia listado y estadisticas. Ahora `empresa_id` es NULL en esos alumnos y grupos, la razon social se guarda en `alumnos.empresa_texto` y los accessors `empresa_nombre`/`empresa_cif` del grupo la resuelven via `candidato->empresaExterna`. Regla general que queda: **solo entra en `empresas` aquello cuyo credito FUNDAE calculamos nosotros**.
- **Codigo de grupo externo en un unico campo de texto (2026-07-28)**: `codigo_grupo_externo` guarda literalmente lo que nos pasa la empresa (`241/3`) y se usa tal cual como nombre del grupo en Moodle, de modo que nuestro aula y su registro FUNDAE comparten identificador. No se parte en dos columnas: el formato lo decide su FUNDAE, no la nuestra. AlumnosIndex lo trocea por `/` solo para pintar la pildora accion/grupo.
- **Pipeline OCR Tesseract local vs Vision API externa (2026-06-01)**: para PDFs escaneados (Ejemplo 3 tipo: impreso y digitalizado) se eligió Tesseract local en lugar de Claude Vision / OpenAI / Google Document AI. Razones: $0/PDF vs $0.02-0.05/PDF, 100% local sin datos personales saliendo del Docker (GDPR), 90-95% accuracy en impreso suficiente para WebCurso, y reaprovecha la lógica regex de Tier 2 (línea de código mínima). Manuscritos seguirían fallando con cualquier OCR, así que Vision tampoco aportaría.
- **Widget V vs XObject ID DESC para PDFs editados múltiples veces (2026-06-01)**: cuando el usuario edita un campo de PDF en un visor (Adobe Reader, navegador), el widget `/V` queda con la PRIMERA grabación mientras los XObject appearance streams acumulan nuevas versiones con IDs crecientes. La última versión visible en pantalla = XObject con ID más alto. Para campos identificables por patrón (email, NIF, fecha, NISS, CIF, teléfono, dropdowns), el pool gana sobre widget V con iteración DESC por ID. Para campos asignados por scoring greedy (nombre/apellidos/cargo/curso/empresa), widget V gana cuando está poblado y el pool itera ASC para tie-breaking estable.
- **Filtrado de contaminación admin por cluster de IDs (2026-06-01)**: en Encomienda la página 1 tiene datos del firmante (admin) que NO son del alumno. Los XObjects de página 1 se identifican por anclas únicas (valores que aparecen una sola vez en el pool, ej. admin name, NIF admin) y se descarta el rango completo `[min - margen, max + margen]` en lugar de remover una ocurrencia por valor. Esto preserva los duplicados que pertenecen al alumno (ej. razón social cuando empresa firmante = empresa del alumno).
- **Title Case selectivo en datos OCR/PDF (2026-06-01)**: PDFs llegan típicamente en MAYÚSCULAS. Se aplica Title Case a `nombre`, `apellido1`, `apellido2`, `cargo` para que la UI muestre "Greicy Lisbeth Barreto Colmenares" en lugar de "GREICY LISBETH BARRETO COLMENARES". `empresa_razon_social` y `curso_pdf` se preservan tal cual porque contienen siglas (S.L., NCS, ChatGPT) que romper sería contraproducente.

## Decisiones de diseno pendientes

- Reorganizacion de categorias en Moodle (Activos/tutor, Repasos, Desactualizados, Plantillas)
