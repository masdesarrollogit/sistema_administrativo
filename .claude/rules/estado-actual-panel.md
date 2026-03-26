# Estado actual del Panel

Snapshot del estado de desarrollo. Ultima actualizacion: marzo 2026.

## Modulos completados

### Gestion de candidatos
- **CandidatosIndex**: listado paginado (15/pagina), busqueda por nombre/email/telefono/razon_social de empresa, filtros por tipo y estatus. Oculta desactivados y cancelados por defecto
- **CandidatoForm**: crear/editar candidato. Autocompletado de curso (busca en MoodleCurso) y empresa (busca segun tipo). Auto-crea empresa si no existe (firstOrCreate)
- **CandidatoEstatus**: gestion detallada — requisitos, archivos adjuntos, configuracion de recordatorios, pausar/reactivar/desactivar
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

### Integracion Moodle API (funcional desde 2026-03-21)
- **MoodleService**: completamente funcional. Ver `api-moodle.md` para referencia completa
- URL interna Docker: `http://moodle_app` con header `Host: localhost:8080`
- URL publica para alumnos: configurada en `MOODLE_PUBLIC_URL` (ej: `https://aula.1curso.com`)
- Token configurado en `.env` como `MOODLE_TOKEN`
- Patron de credenciales Moodle:
  - **Username**: email del alumno
  - **Password**: `ucfirst(nombre) + '4444*'` (ej: `Ana4444*`, `Carlos4444*`)
- Si el usuario ya existe en Moodle: se actualiza la contrasena y se rematricula
- Email de credenciales: desde `info@aula.1curso.com`, CC a `tutorias@webcurso.es`
  - Incluye: usuario, contrasena, URL completa del curso (`/course/view.php?id=X`), fechas inicio/fin, parrafo de bonificacion

### Matriculacion (desarrollado 2026-03-15, mejorado 2026-03-21)
- **GrupoFormativo**: entidad central. Vincula candidato, accion formativa, tutor, empresa, tramo, fechas
- **Alumnos**: asociados a empresa, reutilizables entre grupos (fidelizacion). NIF globalmente unico. Email obligatorio y globalmente unico
- **MatriculacionPanel**: componente Livewire anidado en CandidatoEstatus (cuando estatus=completo)

#### Flujo completo por plataforma:
**Plataforma Moodle (codigo 'm'):**
1. Crear grupo formativo (id_grupo_fundae se asigna automaticamente al crear)
2. Agregar alumnos al grupo (selector muestra email, valida solapamiento de fechas)
3. Generar XML de Inicio de Grupo (descarga directa)
4. Subir XML a FUNDAE (manual)
5. Matricular en Moodle: autodetecta el aula por el moodle_username del tutor → crea grupo Moodle `{accion}/{grupo}` → crea/actualiza usuarios → matricula con fechas inicio/fin → envia email con credenciales

**Plataforma Aulasystem (codigo 'a', www.plataformateleformacion.com):**
1. Crear grupo formativo
2. Agregar alumnos
3. Generar XML de Inicio de Grupo
4. Subir XML a FUNDAE (manual)
5. Botón "Matriculado en Aulasystem" — marca todos los alumnos con `estado_moodle='aulasystem'` y el grupo pasa a `en_curso`. No hay API disponible para esta plataforma

#### Reglas de negocio:
- Un alumno NO puede estar en dos grupos cuyas fechas se solapan (validacion por rango de fechas, no bloqueante si son consecutivos)
- Un grupo permanece abierto hasta 2 dias antes de la fecha de inicio
- Limite FUNDAE: 80 alumnos por tutor por tramo
- id_grupo_fundae se asigna automaticamente al crear el grupo (no requiere boton manual)
- Autodeteccion del aula Moodle: si el tutor tiene moodle_username y hay un solo curso vinculado activo donde el tutor esta matriculado, se usa directamente. Si hay varios, se muestra selector

#### Edicion:
- Grupos `abierto`: se puede editar (tutor, tramo, fechas, jornada, descripcion), eliminar el grupo, editar datos de alumnos
- Grupos `en_curso`: solo se pueden editar datos de alumnos

#### Autocomplete de accion formativa:
- Muestra badge "Moodle" (azul) o "Aulasystem" (ambar) junto a cada resultado

#### Estados Moodle del alumno en pivot `grupo_formativo_alumno`:
- `pendiente` — no procesado
- `creado` — usuario creado en Moodle
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
5. **Importacion de alumnos via XLSX** — plantilla que se envia al candidato
6. **Reorganizacion de categorias Moodle** — Activos/tutor, Repasos, Desactualizados, Plantillas

---

## Decisiones de diseno resueltas

- GrupoFormativo es la entidad central (no Matricula): un grupo tiene accion, tutor, tramo, empresa, alumnos
- Tramo horario pertenece al grupo, no al tutor
- Un alumno no puede estar en dos grupos con fechas solapadas (validacion por rango, no por estado general)
- id_grupo_fundae secuencial por accion formativa, asignado automaticamente al crear el grupo
- Lista de alumnos: ingreso manual (XLSX pendiente)
- Notificacion al alumno: email con credenciales Moodle auto-generadas
- Patron credenciales Moodle: username=email del alumno, password=ucfirst(nombre)+'4444*'
- Email de credenciales: desde info@aula.1curso.com, CC tutorias@webcurso.es
- Plataforma aulasystem (codigo 'a'): sin API disponible, matriculacion se marca manualmente desde el Panel
- Vinculacion AccionFormativa↔Moodle: sin tutor en el pivot (el tutor va en el grupo, no en la accion)
- Facturacion: iniciar con MockFacturacionService

## Decisiones de diseno pendientes

- Estructura exacta de la plantilla XLSX de alumnos
- Reorganizacion de categorias en Moodle (Activos/tutor, Repasos, Desactualizados, Plantillas)
