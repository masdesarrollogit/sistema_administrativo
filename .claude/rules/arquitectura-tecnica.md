# Arquitectura tecnica

## Modelos y relaciones

### Entidad central: Candidato
```
Candidato
├── belongsTo TipoCandidato
├── belongsTo Empresa (si tipo empresa_organizadora)
├── belongsTo EmpresaExterna (si tipo empresa_externa)
├── hasMany RequisitoCandidato
│   └── belongsTo TipoRequisito
├── hasMany NotificacionLog
├── hasMany CandidatoArchivo
├── hasMany GrupoFormativo (grupos bonificados FUNDAE)
└── hasMany MatriculaAutonoma (matriculas autonomas 2x1)
```

### Entidades de empresa
```
Empresa
└── hasMany Grupo (vinculados por CIF)

EmpresaExterna
└── hasMany Candidato
```

### Entidades historicas (comparacion interanual)
```
EmpresaAnterior ──> GrupoAnterior (misma estructura que Empresa/Grupo)
```

### Entidades Moodle (Panel — catalogo local)
```
MoodleCategoria
└── hasMany MoodleCurso
```
**IMPORTANTE:** La tabla `moodle_cursos` del Panel se importo del CSV de webcurso.es (sitio web).
Los cursos reales de Moodle estan en la DB `moodlea1` del contenedor `moodle_mariadb` (516 cursos).
La tabla del Panel debe sincronizarse con la DB real de Moodle, no con el CSV.

### Entidades FUNDAE
```
AlumnoLegacyPool (snapshot one-shot de webcourses2014.tbl_member)
├── nif (UNIQUE) — personal_id legacy normalizado
├── datos personales: nombre, apellidos, email, telefono, niss, fecha_nacimiento
├── códigos FUNDAE mapeados: nivel_estudios, categoria_profesional, grupo_cotizacion_tgss
├── legacy_nid, legacy_company_text, legacy_cif_resuelto (auditoría + lookup CIF empresa)
├── source_mem_id, imported_at
└── Cache local que reemplaza queries en vivo a webcourses2014. Poblada por `alumnos:migrar-legacy`.

AlumnoLegacyCurso (snapshot historial cursos webcourses2014.tbl_member_courses)
├── nif (indexed) — vinculación con Alumno por NIF (no FK, HasMany cross-tabla)
├── course_id, curso_titulo, curso_short_name, curso_horas — info del curso legacy
├── fecha_inicio, fecha_fin
├── estado_curso (Running/Completed/Closed/Upcoming), resultado (Pass/Fail/Not Declared) — guardados pero no renderizados en UI
├── formation_group_alpha (acción), formation_group_number (grupo), grupo_id_fundae — pueden venir vacíos del legacy y rellenarse vía Fase E
├── origen_enriquecimiento (`grupos_fundae` | `participantes_bonificados` | NULL)
├── legacy_company_text, legacy_cif_resuelto
├── source_mc_id (UNIQUE con nif), source_mem_id, imported_at
└── 4,092 entradas migradas (2,994 NIFs únicos). Poblada por `alumnos:migrar-legacy --solo-cursos` o flujo completo.

BonificadoEmailExclusion (NIFs excluidos del envío masivo de email saldo)
├── nif (UNIQUE)
├── nombre, motivo, excluido_por (FK users)
└── Gestionado desde `/webcurso/participantes-bonificados` con botón ✅Activo / 🚫Excluido por fila

ParticipanteBonificado (datos importados de XLS FUNDAE)
├── hasMany inversa desde Alumno (por nif_participante ↔ alumnos.nif)
AccionFormativa (importada de AccionesFormativas.xls, UPSERT por numero_accion)
├── codigo_plataforma: 'm' = aula.1curso.com (Moodle), 'a' = plataformateleformacion.com (Aulasystem)
├── hasMany AccionFormativaMoodleCurso (pivot 1:N con cursos Moodle)
└── hasMany GrupoFormativo

AccionFormativaMoodleCurso (pivot simplificado)
├── accion_formativa_id
├── moodle_course_id (int) — ID del curso en Moodle DB real
├── moodle_fullname (string) — cache del nombre
├── tipo: enum('plantilla', 'activa', 'repaso', 'desactualizado')
│   NOTA: tipo 'tutor' fue eliminado — el tutor pertenece al GrupoFormativo, no a la accion
│   NOTA: NO tiene tutor_id ni idnumber_moodle (simplificado en migracion 2026-03-18)
```

### Entidades de matriculacion (desarrolladas 2026-03-15, actualizadas 2026-03-21)
```
Tutor
├── nombre, apellido1, apellido2, NIF, email, telefono
├── tipo (interno | externo)
├── activo (boolean)
├── moodle_username (string, nullable) — username del tutor en Moodle
│   └── Ejemplos: tutoralvarop (Alvaro), tutorwebcurso@gmail.com (David), traquelg (Raquel)
├── hasMany GrupoFormativo
├── metodo: puedeAceptarEnTramo(tramo, extra=0) — valida limite de 80 alumnos
├── NOTA: tramo_horario NO pertenece al tutor, pertenece al GrupoFormativo

Alumno
├── belongsTo Empresa
├── belongsToMany GrupoFormativo (via grupo_formativo_alumno)
├── hasMany MatriculaAutonoma — matriculas autonomas 2x1 del alumno
├── hasMany ParticipanteBonificado (por nif_participante ↔ nif) — participaciones FUNDAE importadas
├── datos FUNDAE: NIF, NISS, CCC, grupo cotizacion, fecha_nacimiento, sexo, etc.
├── NIF: unico por empresa (unique:alumnos,nif,empresa_id)
├── email: nullable, unico globalmente cuando presente
├── metodo: tieneGrupoActivoEnPeriodo(?fechaInicio, ?fechaFin, ?excluirGrupoId)
│   └── valida solapamiento de fechas, NO bloquea grupos consecutivos de la misma accion
├── metodo: tieneGrupoActivo() — deprecated, llama a tieneGrupoActivoEnPeriodo sin fechas

GrupoFormativo (entidad central de matriculacion)
├── belongsTo Candidato, AccionFormativa, Tutor, Empresa
├── tramo_horario (tramo_1 | tramo_2) — pertenece al GRUPO, no al tutor
├── jornada_laboral (1=completa, 2=media)
├── descripcion — formato FUNDAE: {CIF} {empresa} {alumno|({N})} {curso} {horas}h {tramo}{tutor}
├── id_grupo_fundae — secuencial por accion (consulta tabla grupos importada + grupos_formativos)
│   └── se asigna AUTOMATICAMENTE al crear el grupo (no requiere accion manual)
├── estado: abierto, comunicado, en_curso, completado, cancelado
├── moodle_course_id (int, nullable) — ID del curso Moodle donde se matricularon los alumnos
├── moodle_group_id (int, nullable) — ID del grupo creado en Moodle ({accion}/{grupo})
├── fecha_inicio, fecha_fin
├── belongsToMany Alumno (via grupo_formativo_alumno)
│   └── pivot: moodle_user_id, moodle_username, estado_moodle, intentos_moodle
│   └── estado_moodle: enum('pendiente','creado','matriculado','aulasystem','error')
├── metodo: estaAbierto() — hasta 2 dias antes del inicio
├── metodo: ejecutarEnMoodle(courseId) — flujo completo: grupo Moodle, reactivar tutor, crear/actualizar usuarios, matricular con fechas, enviar emails
├── metodo: asignarIdGrupoFundae() — secuencial consultando ambas tablas
```

### Entidades de autonomos (desarrollado 2026-03-30)
```
MatriculaAutonoma (matricula individual sin grupo FUNDAE — oferta 2x1)
├── belongsTo Candidato — contexto del candidato que trajo al autonomo
├── belongsTo Alumno — el alumno autonomo
├── belongsTo AccionFormativa — que curso
├── belongsTo Tutor — tutor asignado
├── belongsTo Empresa — empresa del autonomo
├── fecha_inicio (nullable), fecha_fin (nullable) — sin restriccion FUNDAE
├── moodle_course_id, moodle_user_id, moodle_username — datos de matricula Moodle
├── estado: enum('pendiente','matriculado','error')
├── intentos_moodle, ultimo_error_moodle — tracking de errores
├── metodo: ejecutarEnMoodle(courseId) — matricula individual: crear/actualizar usuario, matricular, enviar email
├── NO tiene: id_grupo_fundae, moodle_group_id, tramo_horario, jornada_laboral
└── NO genera XML ni requiere PDF FUNDAE
```

### Autenticacion
```
User (Spatie HasRoles, HasPermissions)
```

## Scopes clave

| Modelo | Scope | Uso |
|---|---|---|
| Candidato | `pendientes()` | Candidatos con requisitos sin completar |
| Candidato | `completos()` | Candidatos con todos los requisitos completados |
| Candidato | `listosParaRecordatorio()` | Evalua frecuencia, dia de la semana (lunes) y ultimo envio |
| Empresa | `activas()` | No anuladas ni bloqueadas |
| Empresa | `conDatos()` | Filtra registros vacios (sin CIF ni razon social) |
| Empresa | `sinGrupos()` | Empresas sin grupos formativos |
| Grupo | `validos()` | Grupos activos |
| Grupo | `teleformacion()` / `presencial()` | Filtro por modalidad |
| TipoCandidato | `activos()` | Ordenados por campo 'orden' |
| TipoRequisito | `obligatorios()` | Solo requisitos obligatorios |
| RequisitoCandidato | `pendientes()` / `completados()` | Filtro por estado |
| AccionFormativa | `activas()` | Estado = Alta |
| AccionFormativa | `teleformacion()` | Modalidad = Teleformacion |
| Tutor | `activos()` | Solo tutores activos |
| Tutor | `internos()` | Solo tipo interno |
| Alumno | `activos()` | Solo activos |
| Alumno | `disponibles()` | Sin grupo activo (abierto/comunicado/en_curso) |
| GrupoFormativo | `abiertos()` | Estado abierto + mas de 2 dias antes del inicio |
| GrupoFormativo | `enCurso()` | Estado en_curso |

## Patrones Livewire

- **Componentes de clase** (no Volt) como patron principal
- **WithPagination** para todos los listados (12-25 items/pagina segun vista)
- **WithFileUploads** para adjuntos en CandidatoEstatus e ImportarCsv
- **Trait HasEmpresaModal** compartido entre EmpresasIndex, EmpresasSinGrupos y ParticipantesBonificadosIndex
- **Query string params** para filtros persistentes en URL (filtros se mantienen al paginar/navegar)
- **Autocompletado** en CandidatoForm: busca cursos en MoodleCurso y empresas segun tipo
- **Autocompletado** en MatriculacionPanel: busca acciones formativas por denominacion o numero
- **Componente anidado**: MatriculacionPanel se incluye dentro de CandidatoEstatus cuando estatus=completo
- **Eventos Livewire**: dispatch('import-completed') tras importaciones

## Servicios

### CsvImportService (`app/Services/Webcurso/CsvImportService.php`)
- Importacion de empresas: CSV o XLS/XLSX, UPSERT por CIF
- Importacion de grupos: CSV o XLS/XLSX, TRUNCATE + INSERT, excluye "NO VA", extrae CIF de denominacion
- Importacion de participantes: XLS/XLSX con PhpSpreadsheet, TRUNCATE + INSERT
- Importacion de acciones formativas: XLS/XLSX, UPSERT por numero_accion
- Soporte dual CSV/XLS: metodo `leerFilas()` detecta extension y usa fgetcsv o PhpSpreadsheet
- Conversion de fechas Excel: detecta numeros seriales (>25000) y convierte con `Date::excelToDateTimeObject()`
- Conversion de formatos EU: numeros (1.234,56), fechas (dd/mm/YYYY), porcentajes
- Logging detallado con contadores (procesados, errores, omitidos)

### FundaeXmlService (`app/Services/Webcurso/FundaeXmlService.php`)
- Genera 3 tipos de XML FUNDAE validados contra XSD:
  - `generarXmlAccionesFormativas(accionIds)` — Alta de acciones formativas
  - `generarXmlInicioGrupo(grupoIds)` — Inicio de grupo formativo (patron real WebCurso)
  - `generarXmlFinalizacionGrupo(grupoIds)` — Finalizacion de grupo (participantes + costes)
- Datos del centro desde `config/webcurso.php`
- Esquemas XSD y ejemplos en `storage/fundae/`

### MoodleService (`Modules/Moodle/Services/MoodleService.php`)
- Cliente REST API de Moodle (singleton). **Estado: FUNCIONAL**
- Autenticacion: token via `config('moodle.token')`, header `Host` via `MOODLE_HOST_OVERRIDE`
- Metodos disponibles: `createUser`, `updateUserPassword`, `findUserByUsername`, `enrolInCourse` (con timestart/timeend/suspend), `getUserCourses`, `createGroup`, `addUsersToGroup`, `searchCourses`, `getUserGrades`, `testConnection`, `call` (generico)
- Ver `api-moodle.md` para documentacion completa de cada metodo

## Sistema de cron

### Definicion (`routes/console.php`)
| Comando | Horario | Timezone | Descripcion |
|---|---|---|---|
| `candidatos:enviar-recordatorios` | Diario 09:00 | Europe/Madrid | Envia recordatorios a candidatos con requisitos pendientes |
| `candidatos:enviar-resumen` | Diario 13:00 | Europe/Madrid | Envia resumen de pendientes al administrador |

Ambos notifican por email en caso de fallo.

### Ejecucion
El contenedor `scheduler` en Docker ejecuta `php artisan schedule:run` cada 60 segundos en un loop infinito. Tiene restart policy `unless-stopped` para recuperacion automatica.

## Infraestructura Docker (compose.yaml)

### Servicios

| Servicio | Imagen | Puertos | Proposito |
|---|---|---|---|
| **laravel.test** | sail-8.5/app (PHP 8.5) | 80 (app), 5173 (Vite HMR) | Aplicacion principal |
| **scheduler** | sail-8.5/app | — | Cron: `schedule:run` cada 60s |
| **mysql** | mysql:8.4 | 3306 | Base de datos principal + testing DB |
| **redis** | redis:alpine | 6379 | Cache y sesiones |
| **meilisearch** | getmeili/meilisearch:latest | 7700 | Busqueda full-text |
| **mailpit** | axllent/mailpit:latest | 1025 (SMTP), 8025 (UI web) | Captura de emails en desarrollo |
| **selenium** | selenium/standalone-chromium | — | Tests de navegador (Dusk) |
| **phpmyadmin** | phpmyadmin:latest | 8888 | Gestion visual de MySQL del Panel |

### Servicios Moodle (compose separado)

| Servicio | Imagen | Puertos | Proposito |
|---|---|---|---|
| **moodle_app** | moodle_aula1-moodle | 8080 | Moodle LMS (aula.1curso.com) |
| **moodle_mariadb** | mariadb:10.11 | 3306 (interno) | Base de datos Moodle |
| **moodle_phpmyadmin** | phpmyadmin:latest | 8081 | Gestion visual de Moodle DB |

**Acceso Moodle DB:** DB=`moodlea1`, User=`moodleusera1`, tablas sin prefijo `mdl_`

### Volumenes persistentes
- `sail-mysql` — datos MySQL
- `sail-redis` — datos Redis
- `sail-meilisearch` — indices de busqueda

### Red
- Bridge personalizada `sail` — todos los servicios se comunican por nombre de servicio

### Health checks
- MySQL: `mysqladmin ping` cada 5s, 3 reintentos
- Redis: `redis-cli ping` cada 3s, 3 reintentos

### Scheduler (detalle)
```yaml
scheduler:
  image: sail-8.5/app
  command: >
    bash -c "while true; do
      php /var/www/html/artisan schedule:run --no-interaction;
      sleep 60;
    done"
  depends_on: [laravel.test]
  restart: unless-stopped
```

### Accesos en desarrollo
| Servicio | URL |
|---|---|
| Aplicacion Panel | http://localhost |
| Moodle LMS | http://localhost:8080 |
| phpMyAdmin Moodle | http://localhost:8081 |
| Mailpit (emails) | http://localhost:8025 |
| phpMyAdmin Panel | http://localhost:8888 |
| Meilisearch | http://localhost:7700 |

### Comandos de gestion
```bash
./vendor/bin/sail up -d          # Levantar todos los servicios
./vendor/bin/sail down           # Detener servicios
./vendor/bin/sail shell          # Terminal dentro del contenedor PHP
./vendor/bin/sail mysql          # Terminal MySQL
./vendor/bin/sail redis          # Terminal Redis
./vendor/bin/sail logs scheduler # Ver logs del scheduler
composer dev                     # Levanta server + queue + logs + vite concurrentemente
composer test                    # Ejecuta tests Pest con SQLite in-memory
```

## Configuracion de dominio

### config/candidatos.php
- `recordatorios.dias_entre_envios` — dias entre recordatorios (env: CANDIDATOS_DIAS_ENTRE_RECORDATORIOS, default: 7)
- `recordatorios.max_recordatorios` — maximo por candidato (env: CANDIDATOS_MAX_RECORDATORIOS, default: 5)
- `recordatorios.copia_email` — BCC de recordatorios (env: CANDIDATOS_COPIA_EMAIL)
- `recordatorios.activo` — activar/desactivar sistema (env: CANDIDATOS_RECORDATORIOS_ACTIVOS, default: true)
- `empresas.auto_crear` — crear empresa automaticamente si no existe
- `contacto.email/telefono/whatsapp` — datos de contacto en emails

### config/webcurso.php
- Configuracion de vistas: columnas de tabla y campos de modal para empresas y empresas_sin_grupos
- Flags de modulos: modulo_candidato, modulo_saldo

### config/moodle.php
- `url` — URL interna Docker (env: MOODLE_URL, valor: `http://moodle_app`)
- `token` — token de API (env: MOODLE_TOKEN)
- `public_url` — URL publica para enlaces en emails al alumno (env: MOODLE_PUBLIC_URL, default: `https://aula.1curso.com`)
- `mail_from` — remitente emails de credenciales (env: MOODLE_MAIL_FROM, default: `info@aula.1curso.com`)

### config/mail.php — mailer dedicado Moodle
Mailer `moodle` separado del mailer general (`smtp` con Gmail). Usado exclusivamente por `CredencialesMoodleMail`:
- `MOODLE_MAIL_MAILER` — smtp
- `MOODLE_MAIL_HOST` — `smtp.gmail.com` (mismo SMTP que el mailer general)
- `MOODLE_MAIL_PORT` — 587
- `MOODLE_MAIL_USERNAME` — `saldoswebcurso@gmail.com`
- `MOODLE_MAIL_PASSWORD` — app password de Gmail
- `MOODLE_MAIL_ENCRYPTION` — tls
- `MOODLE_MAIL_FROM` — `tutorias@webcurso.es` (alias "Enviar como" en Gmail)
- CC: `administracion@webcurso.es`
- En produccion: `MOODLE_HOST_OVERRIDE` NO es necesaria (Moodle esta en servidor separado con URL publica)

## Testing
- Framework: **Pest**
- Base de datos: SQLite in-memory (phpunit.xml)
- Queue: sync
- Cache/Session: array
- Comando: `composer test` o `./vendor/bin/sail php artisan test`
