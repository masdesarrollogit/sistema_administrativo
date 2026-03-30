# Estado actual del Panel

Snapshot del estado de desarrollo. Ultima actualizacion: marzo 2026.

## Modulos completados

### Gestion de candidatos
- **CandidatosIndex**: listado paginado (15/pagina), busqueda por nombre/email/telefono/razon_social de empresa, filtros por tipo y estatus. Oculta desactivados y cancelados por defecto. Boton eliminar candidato (elimina requisitos y archivos adjuntos asociados)
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

---

### Tutores (desarrollado 2026-03-15)
- **TutoresIndex**: CRUD con filtros (nombre, tipo, activo). Modal crear/editar
- Muestra alumnos por tramo (T1/T2) con limite de 80
- tramo_horario pertenece al GrupoFormativo, NO al tutor
- Campo `moodle_username` (string, nullable) — nombre de usuario del tutor en Moodle, usado para autodetectar el aula al matricular
  - Ejemplos actuales: `tutoralvarop` (Alvaro), `tutorwebcurso@gmail.com` (David), `traquelg` (Raquel)

### Acciones Formativas FUNDAE (desarrollado 2026-03-15, actualizado 2026-03-21)
- **AccionesFormativasIndex**: listado paginado con estadisticas de vinculacion Moodle
- Filtros: denominacion, estado, area profesional, plataforma (m/a)
- Importacion XLS desde FUNDAE (UPSERT por numero_accion)
- Vinculacion con cursos reales de Moodle: autocomplete busca cursos via API Moodle (`core_course_search_courses`)
- Tipos de vinculacion: `activa`, `plantilla`, `repaso`, `desactualizado` (se elimino el tipo `tutor` — el tutor pertenece al grupo, no a la accion)
- La tabla pivot `accion_formativa_moodle_curso` NO tiene tutor_id ni idnumber_moodle (simplificada)

### Generacion XML FUNDAE (desarrollado 2026-03-15)
- **FundaeXmlService**: genera 3 tipos de XML (Acciones Formativas, Inicio Grupo, Finalizacion Grupo)
- Esquemas XSD y ejemplos en `storage/fundae/`

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

---

## Inventario tecnico

| Aspecto | Cantidad |
|---|---|
| Modelos | 20 |
| Componentes Livewire | 13 |
| Clases Mail | 5 |
| Comandos Artisan | 4 |
| Servicios | 3 (CsvImportService, FundaeXmlService, MoodleService) |
| Migraciones | 31 |
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
- Alumnos fidelizados: visibles en el panel de gestion por empresa, agregables con un clic
- Plataforma aulasystem (codigo 'a'): sin API disponible, matriculacion se marca manualmente desde el Panel
- Vinculacion AccionFormativa↔Moodle: sin tutor en el pivot (el tutor va en el grupo, no en la accion)
- Facturacion: iniciar con MockFacturacionService
- Campo "nombre de curso" eliminado del formulario de candidato (era redundante con la accion formativa del grupo)

## Decisiones de diseno pendientes

- Reorganizacion de categorias en Moodle (Activos/tutor, Repasos, Desactualizados, Plantillas)
