# Estado actual del Panel

Snapshot del estado de desarrollo. Ultima actualizacion: 2026-04-13.

## Modulos completados

### Gestion de candidatos
- **CandidatosIndex**: listado paginado (15/pagina), busqueda por nombre/email/telefono/razon_social de empresa, filtros por tipo y estatus. Oculta desactivados y cancelados por defecto. Boton eliminar candidato (elimina requisitos y archivos adjuntos asociados). **Icono de telefono clicable** junto al nombre del candidato (enlace `tel:` para integracion con Momena u otra app VoIP)
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

### Identificacion visual en AlumnosIndex (actualizado 2026-04-07)
- Badge ambar "2x1" junto al nombre del alumno en la tabla cuando tiene matriculas autonomas
- Columna Grupos: muestra badges independientes que pueden coexistir:
  - Grupos FUNDAE del Panel (activos + total) — badge verde/gris
  - Participaciones FUNDAE importadas (bonificados_total) — badge esmeralda, visible solo si no tiene grupos del Panel
  - Matriculas autonomas — badge ambar
- Filtro "Tipo": Todos / Con grupos FUNDAE (incluye tanto grupos del Panel como participaciones importadas) / Autonomos (2x1)
- Modal "Historial": tres secciones independientes (pueden aparecer simultaneamente):
  1. **Grupos bonificados** (badge FUNDAE azul) — grupos creados en el Panel via GrupoFormativo
  2. **Participacion FUNDAE importada** (badge IMPORTADO esmeralda) — datos de `participantes_bonificados` cruzados por NIF. Muestra: grupo, PIF, fechas, estado participante, estado grupo
  3. **Matriculas autonomas** (badge 2x1 ambar) — matriculas individuales sin FUNDAE
- Boton "Historial" visible cuando hay datos en cualquiera de las tres fuentes
- `Alumno` model: relaciones `gruposFormativos()`, `matriculasAutonomas()`, `participantesBonificados()` (HasMany por NIF)
- `AlumnosIndex`: `withCount` incluye `bonificados_total`, `filtroTipo='fundae'` usa `orWhereHas('participantesBonificados')`

---

## Inventario tecnico

| Aspecto | Cantidad |
|---|---|
| Modelos | 23 (+ AlumnoLegacyPool, AlumnoLegacyCurso, BonificadoEmailExclusion) |
| Componentes Livewire | 13 |
| Clases Mail | 5 |
| Comandos Artisan | 8 (+ alumnos:migrar-legacy, alumnos:enriquecer-cursos-legacy) |
| Servicios | 3 (CsvImportService, FundaeXmlService, MoodleService) |
| Migraciones | 36 |
| Configs de dominio | 3 |

---

## Modulos pendientes de disenar y desarrollar

1. **Seguimiento academico** — progreso en Moodle (Fase 5)
2. **Integracion Zoho CRM** — sincronizacion de candidatos
3. **Facturacion** — Zoho Books (iniciar con mocks)
4. **Cierre de expediente FUNDAE** (Fase 7)
5. **Reorganizacion de categorias Moodle** — Activos/tutor, Repasos, Desactualizados, Plantillas

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

## Decisiones de diseno pendientes

- Reorganizacion de categorias en Moodle (Activos/tutor, Repasos, Desactualizados, Plantillas)
