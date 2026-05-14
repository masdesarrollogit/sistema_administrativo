# Reportes Moodle — Módulo completo (Reportes 1 al 7)

Documento técnico del módulo **Reportes Moodle**, implementado entre el 2026-05-06 y el 2026-05-14. Cubre las **6 Fases originales** del documento de Gemini más el **Reporte 7 (No aptos / Reinicios)** añadido posteriormente.

## Vocabulario clave

- **Fase** = etapa de desarrollo / entrega incremental (Fase 1, Fase 2…). Es un término **de gestión interna**: dice cuándo construimos qué. No es algo que el alumno "tenga" ni "esté en".
- **Reporte** = unidad funcional / producto. Es lo que el equipo administrativo de WebCurso usa día a día. Cada Reporte tiene su nombre, su regla de detección, su tabla, sus notificaciones y su log. Un Reporte se construye en una Fase.
- **Filtro** = control de UI dentro de un Reporte que recorta su contenido (tutor, empresa, acción formativa, búsqueda).
- **Estado / badge** = clasificación visual del alumno dentro de un Reporte (ej. dentro de "No conectados" hay dos badges: amarillo "Nunca entró al sitio" y naranja "No entró al curso").

El módulo identifica de forma preventiva a los alumnos en riesgo de no cumplir los requisitos FUNDAE (75% de actividades + cuestionario final) y dispara comunicaciones automáticas de rescate al alumno y al tutor.

Origen del requisito: documento de Gemini en [`docs/Reportes_moodle_2.docx`](Reportes_moodle_2.docx) que define 6 Reportes a entregar en 6 Fases. **Las 6 Fases originales más el Reporte 7 (No aptos / reinicios) añadido posteriormente están entregadas**: Reportes 1 (No conectados), 2 (Inactivos), 3 (Riesgo crítico), 4 (Pre-cierre), 5 (Apto sin examen), 6 (Aprobado / Finalizado con éxito) y 7 (No aptos / Reinicios). **El módulo está completo.**

Fechas:
- **2026-05-06** — Reporte 1 (No conectados)
- **2026-05-07** — Reporte 2 (Inactivos)
- **2026-05-13** — Refactor de vocabulario, unificación de "No conectados", dashboard híbrido y refinamiento de la regla de Inactivo
- **2026-05-14** — Reportes 3 (Riesgo crítico), 4 (Pre-cierre), 5 (Apto sin examen), 6 (Aprobado) y 7 (No aptos / Reinicios)

---

## Índice

1. [Visión general del módulo](#vision)
2. [Decisiones de diseño](#decisiones)
3. [Modelo de datos](#modelo-datos)
4. [Servicios](#servicios)
5. [Comandos artisan y schedules](#comandos)
6. [Mailables y plantillas](#mailables)
7. [UI Livewire](#ui)
8. [Configuración](#config)
9. [Pre-requisito: funciones webservice de Moodle](#moodle-ws)
10. [Verificación end-to-end](#verificacion)
11. [Resumen de archivos creados/modificados](#archivos)
12. [Estado final del módulo](#pendiente)
13. [Cambios 2026-05-13 (refactor vocabulario + dashboard híbrido)](#cambios-2026-05-13)
14. [Cambios 2026-05-14 (Reportes 3-7 + cierre del módulo)](#cambios-2026-05-14)

---

<a id="vision"></a>
## 1) Visión general del módulo

`/webcurso/reportes-moodle` es un **dashboard** con KPIs clicables que dan acceso a cada Reporte. Solo procesa alumnos cuyo grupo formativo está activo hoy (`estado=en_curso` AND `fecha_inicio<=hoy<=fecha_fin`, año 2026).

### Lista completa de Reportes

Cada Fase de desarrollo entrega UN Reporte completo (vista + detección + notificaciones + log).

| # | Reporte | Fase | Badge | Regla | Cuándo aparece |
|---|---|---|---|---|---|
| 1 | **No conectados** | Fase 1 ✅ | 🟡 Nunca entró al sitio<br>🟠 No entró al curso | `lastaccess_curso = 0`. Sub-badge según `lastaccess_global` | Durante el curso, email los días 3/6/9 desde inicio (tope 3) |
| 2 | **Inactivos** | Fase 2 ✅ | 🟣 Indigo | Entró al curso alguna vez y lleva >3 días sin volver. Email solo si `aprobado=false` | Durante el curso, throttle 72h |
| 3 | **Riesgo crítico** | Fase 3 ✅ | 🔴 Rojo | Entró al curso AND `nota_total < 50` AND `pct_tiempo_transcurrido >= 50%`. Email semanal (throttle 168h), sin tope | Desde la mitad del tiempo del curso hasta `fecha_fin` |
| 4 | **Pre-cierre** | Fase 4 ✅ | 🟧 Ámbar oscuro | `cuestionario_final_realizado = false` (independiente de la nota), últimas 72h. Email diario (throttle 23h), sin tope. **Prioridad máxima** sobre R1/R2/R3 | Solo en las últimas 72h antes de `fecha_fin` |
| 5 | **Apto sin examen** | Fase 5 ✅ | 🟡 Amarillo claro | `nota_total >= 50` AND `cuestionario_final_realizado = false`. Email semanal con tono positivo. **R4 tiene prioridad**: si está en Pre-cierre no se le envía R5. | Continuo durante el curso desde que cruza los 50 pts |
| 6 | **Finalizado con éxito (Apto)** | Fase 6 ✅ | 🟢 Verde | `aprobado = true` (nota>=50 AND cuestionario final). Email único de felicitación al detectar la aprobación (verifica log previo). | Continuo desde que se cumple la condición |
| 7 | **No aptos / Reinicios** | Fase 7 ✅ | 🟥 Rojo oscuro | `nota_total < 50` AND `cuestionario_final_realizado = false` AND `fecha_fin < hoy`. Registro permanente en `alumno_no_aptos`. Email semanal con `mailto:` admin hasta 4 ofrecimientos en 30 días. Cierre manual con botón "Marcar reiniciado". | **Solo después de `fecha_fin`** del curso |

### Cómo se relacionan los Reportes a lo largo del tiempo

Un alumno puede aparecer en distintos Reportes según el momento del curso. Ejemplo: curso del **1 abril → 30 mayo** (60 días), alumno con **20 puntos** y **5 días sin entrar**.

| Día del calendario | Reporte aplicable |
|---|---|
| Día 5 desde inicio | **R1** si no entró nunca / **R2** (Inactivo) si entró y está ausente |
| Día 30 (mitad del tiempo) en adelante | **R3 (Riesgo crítico)** porque tiene <50 pts |
| Día 27 antes del fin (= día 57 del curso) | **R4 (Pre-cierre)** porque no ha hecho el cuestionario final |
| Día 60+ (curso cerrado, `fecha_fin` ya pasó) | **R7 (No apto)** porque cerró con <50 pts |

Un mismo alumno puede aparecer **simultáneamente en varios Reportes** (ej. Inactivo + Riesgo crítico). Cada Reporte tiene su propio mensaje y su propio cron. El dashboard suma los KPIs y la tabla filtra según el Reporte seleccionado.

### Por qué "No apto" solo aparece después de `fecha_fin`

Mientras el curso sigue activo, un alumno con <50 puntos **todavía puede recuperarse**. Por eso aparece en Reportes de alerta (Inactivo, Riesgo crítico, Pre-cierre) que disparan acciones de rescate. **"No apto"** es el **veredicto final** que solo se emite cuando el curso ya cerró y no hay marcha atrás: ahí se gestiona el cierre administrativo, no la motivación.

### Vocabulario "aprobado"

Aprobado FUNDAE = `nota_total >= 50` **AND** `cuestionario_final_realizado = true`. Si el alumno tiene 50+ puntos pero no ha hecho el cuestionario final, **NO** está aprobado todavía (entra en Reporte 5 "Apto sin examen" hasta que rinda el cuestionario).

> **Importante:** "Inactivo" (estado) y "recibir email de inactividad" (acción) son cosas distintas. Un alumno aparece como Inactivo en cuanto cumple `>3d sin entrar`. Pero el email de rescate solo se envía si además `aprobado = false`. Si ya aprobó el curso, sigue siendo "Inactivo" en la UI pero no se le insiste por email (no hay nada que rescatar).

---

<a id="decisiones"></a>
## 2) Decisiones de diseño

| Decisión | Elegida | Motivo |
|---|---|---|
| Snapshot diario en tabla local vs. queries en vivo | **Snapshot 02:00 Madrid + botón "Refrescar todos ahora"** | UI carga instantánea, permite detección de inactividad por comparativa día-a-día |
| Acceso a Moodle | **REST API (14 + 4 funciones)** | Sin DB directa; basta con habilitar funciones en el rol del token |
| Una vista vs. seis | **Vista única con filtros por color** | Reduce navegación, KPIs cobran sentido |
| Tope de reenvíos Fase 1 (alumno no conectado) | Configurable, **default 3** (días [3, 6, 9]) | Evita spam: tras 3 emails escala solo al tutor |
| Tope de reenvíos Fase 2 (inactivo) | **Sin tope**, throttle 72h | Doc: "Cada 72h hasta que vuelva o termine el curso" |
| Email tutor | **Un solo email los lunes con dos secciones** | Reduce ruido para el tutor |
| Permisos | **Solo admin/SuperAdmin** | Tutores reciben info por email; login propio no en alcance |
| Mailpit en local | **Forzado** (regla innegociable) | No enviar emails a destinatarios reales en dev |

---

<a id="modelo-datos"></a>
## 3) Modelo de datos

### Tabla `alumno_progreso_moodle`

Snapshot diario por (pivot, fecha). Una fila por alumno-grupo-día.

```
id, alumno_id (FK), grupo_formativo_alumno_id (FK pivot)
moodle_user_id, moodle_course_id
lastaccess_global (uint, timestamp)        — 0 = nunca al sitio
lastaccess_curso  (uint, timestamp)        — 0 = nunca al curso
nunca_entro_sitio (bool)
nunca_entro_curso (bool)
progress (decimal 5,2)                     — % completion si enablecompletion=true
completed (bool)
aprobado (bool)                            — nota_total >= 50 AND cuestionario_final_realizado
cuestionario_final_realizado (bool)        — grade_item.is_final_quiz tiene nota válida
nota_total (decimal 7,2)                   — itemtype=course
nota_max   (decimal 7,2)
dias_inactivo (uint, nullable)             — null si nunca entró al curso
inactivo (bool, indexed)                   — lastaccess_curso > 0 AND dias_inactivo > umbral
fecha_snapshot (date, indexed)
ultimo_refresh_at (timestamp)
fuente (enum: cron|manual)
timestamps
UNIQUE (grupo_formativo_alumno_id, fecha_snapshot)
```

Migraciones:
- [`2026_05_06_000001_create_alumno_progreso_moodle_table.php`](../database/migrations/2026_05_06_000001_create_alumno_progreso_moodle_table.php)
- [`2026_05_07_000001_add_inactividad_y_notas_to_alumno_progreso_moodle.php`](../database/migrations/2026_05_07_000001_add_inactividad_y_notas_to_alumno_progreso_moodle.php)
- [`2026_05_13_000001_add_aprobado_y_examen_to_alumno_progreso_moodle.php`](../database/migrations/2026_05_13_000001_add_aprobado_y_examen_to_alumno_progreso_moodle.php)

### Tabla `alumno_notificaciones_log`

Auditoría de emails enviados desde el módulo.

```
id, alumno_id (FK nullable), tutor_id (FK nullable), grupo_formativo_id (FK nullable)
tipo (string 50)                       — alumno_no_conectado | alumno_inactivo | tutor_reporte_semanal
fase (uint8)                           — 1 ó 2
canal (string 20, default 'email')
destinatario_email
payload (json)                         — datos relevantes (días, intento, tope, n alumnos…)
exitoso (bool)
error_message (text nullable)
enviado_at (timestamp)
INDEX (alumno_id, tipo, enviado_at), (grupo_formativo_id, tipo), (tutor_id, tipo, enviado_at)
```

Migración: [`2026_05_06_000002_create_alumno_notificaciones_log_table.php`](../database/migrations/2026_05_06_000002_create_alumno_notificaciones_log_table.php)

> Decisión: **no se reutilizó `notificaciones_log`** porque está atada a `candidato_id` y aquí el destinatario puede ser alumno o tutor.

### Tabla `alumno_calificaciones_moodle` (Fase 2)

Detalle de notas por actividad por snapshot. Permite el modal "Ver notas" y queda como base histórica para Fase 3.

```
id, alumno_progreso_moodle_id (FK)
moodle_grade_item_id, itemname, itemtype, itemmodule
grade, grademax, grademin (decimal)
percentageformatted, lettergrade
is_course_total (bool)                 — la fila del Total del curso (itemtype=course)
is_final_quiz (bool)                   — el último quiz del curso
graded_at (uint timestamp)
timestamps
INDEX (alumno_progreso_moodle_id, is_course_total) y (…, is_final_quiz)
```

Migración: [`2026_05_07_000002_create_alumno_calificaciones_moodle_table.php`](../database/migrations/2026_05_07_000002_create_alumno_calificaciones_moodle_table.php)

### Tabla `grupo_formativo_alumno` (modelo Pivot añadido)

Ya existía como pivot puro. Se le añadió un modelo Eloquent [`GrupoFormativoAlumno`](../app/Models/GrupoFormativoAlumno.php) para poder hacer queries con eager-loading.

---

<a id="servicios"></a>
## 4) Servicios

### `MoodleService` (extendido)

[`Modules/Moodle/Services/MoodleService.php`](../Modules/Moodle/Services/MoodleService.php). Wrapper REST. Métodos nuevos:

- `getUsersByIds(array $userIds): array` — batch de `core_user_get_users_by_field` para obtener `lastaccess` global.
- `getUserGradeItems(int $userId, int $courseId): array` — `gradereport_user_get_grade_items` (JSON estructurado, no HTML).

### `MoodleReportingService`

[`app/Services/Webcurso/MoodleReportingService.php`](../app/Services/Webcurso/MoodleReportingService.php). Capa de orquestación pensada para reportes (batch + manejo de errores). Métodos:

- `getLastAccessGlobalBatch(array $moodleUserIds, int $loteSize = 50): array` — devuelve `[moodle_user_id => lastaccess]` chunked.
- `getUserCourseStats(int $moodleUserId, int $moodleCourseId): ?array` — del `core_enrol_get_users_courses`, filtra el curso pedido y devuelve `lastaccess`, `progress`, `completed`, `enablecompletion`.
- `getUserGrades(int $moodleUserId, int $moodleCourseId): array` — parsea `gradereport_user_get_grade_items` y devuelve `items + total + final_quiz` con flags.

### `AlumnoProgresoSnapshotter`

[`app/Services/Webcurso/AlumnoProgresoSnapshotter.php`](../app/Services/Webcurso/AlumnoProgresoSnapshotter.php). Orquesta el snapshot diario:

1. Selecciona pivots: `estado_moodle='matriculado'` AND grupo `estado='en_curso'` AND `fecha_inicio<=hoy<=fecha_fin` AND año 2026.
2. Limpia snapshots de hoy cuyos pivots ya no son elegibles (curso finalizado, fecha futura, etc.).
3. Batch de `lastaccess` global.
4. Por alumno consulta stats del curso (lastaccess, progress) y notas (gradeitems).
5. Calcula `dias_inactivo` y flag `inactivo` (umbral configurable, default 3).
6. UPSERT en `alumno_progreso_moodle` keyed por (pivot, fecha_snapshot).
7. Reemplaza calificaciones del día (delete + insert) en `alumno_calificaciones_moodle`.

Soporta `--dry-run` y `--alumno-id=N`.

---

<a id="comandos"></a>
## 5) Comandos artisan y schedules

| Comando | Schedule | Función |
|---|---|---|
| `reportes-moodle:snapshot` | Diario 02:00 Madrid | Llena `alumno_progreso_moodle` y `alumno_calificaciones_moodle` |
| `reportes-moodle:notificar-no-conectados` | Diario 10:00 Madrid | Email al alumno días 3/6/9 desde inicio (Fase 1, tope 3) |
| `reportes-moodle:notificar-inactivos` | Diario 10:15 Madrid | Email al alumno inactivo (Fase 2, throttle 72h, sin tope) |
| `reportes-moodle:notificar-tutores` | Lunes 09:00 Madrid | Email semanal al tutor con dos secciones (no conectados + inactivos) |

Todos en `Europe/Madrid` con `onFailure` que loguea el error.

Schedule registrado en [`routes/console.php`](../routes/console.php).

Ubicación de los comandos: [`app/Console/Commands/ReportesMoodle/`](../app/Console/Commands/ReportesMoodle/).

---

<a id="mailables"></a>
## 6) Mailables y plantillas

| Mailable | Destinatario | Trigger | Vista |
|---|---|---|---|
| [`AlumnoNoConectadoMail`](../app/Mail/AlumnoNoConectadoMail.php) | Alumno | Fase 1 — días 3/6/9 desde inicio | [`emails/alumno-no-conectado.blade.php`](../resources/views/emails/alumno-no-conectado.blade.php) |
| [`AlumnoInactivoMail`](../app/Mail/AlumnoInactivoMail.php) | Alumno | Fase 2 — inactivo >3d, throttle 72h | [`emails/alumno-inactivo.blade.php`](../resources/views/emails/alumno-inactivo.blade.php) |
| [`TutorReporteSemanalMail`](../app/Mail/TutorReporteSemanalMail.php) | Tutor | Lunes — Fases 1+2 unificadas | [`emails/tutor-reporte-semanal.blade.php`](../resources/views/emails/tutor-reporte-semanal.blade.php) |

Todos los mailables:
- Mailer: `moodle` (alias `tutorias@webcurso.es` sobre Gmail SMTP).
- CC: `administracion@webcurso.es` (configurable vía `REPORTES_MOODLE_COPIA_ADMIN`).
- En `APP_ENV != production` salen a Mailpit (regla innegociable, ya cubierta por `AppServiceProvider`).

El email del tutor contiene dos tablas: alumnos no conectados (color `slate`) e inactivos (color `indigo`) con teléfono clic-to-call para llamar al alumno.

---

<a id="ui"></a>
## 7) UI Livewire

[`app/Livewire/Webcurso/ReportesMoodleIndex.php`](../app/Livewire/Webcurso/ReportesMoodleIndex.php) + [`resources/views/livewire/webcurso/reportes-moodle-index.blade.php`](../resources/views/livewire/webcurso/reportes-moodle-index.blade.php).

Ruta: `/webcurso/reportes-moodle` (auth + role admin/SuperAdmin) en [`routes/webcurso.php`](../routes/webcurso.php). Enlace en sidebar: [`resources/views/components/sidebar-layout.blade.php`](../resources/views/components/sidebar-layout.blade.php).

### Componentes visuales

- **Header**: título + botón "↻ Refrescar todos ahora" (ejecuta el snapshotter inline con fuente=manual).
- **6 KPIs** con barra lateral del color del badge: Total · Nunca entró al sitio (amarillo) · No entró al curso (naranja) · Inactivos (indigo) · Conectados (verde) · % en riesgo (rojo).
- **Leyenda**: 6 píldoras con su color, las 4 activas (Fases 1+2) y las 2 futuras (Fases 3 y 6) al 50% de opacidad.
- **Filtros**: Buscar libre + Color + Tutor + Empresa + Acción formativa. URL queryString.
- **Tabla** (10 columnas): Estado, Alumno, Usuario, Curso, Tutor, Grupo, Fechas, Días desde inicio, **Total curso (barra %)**, Avisos.
  - Estado: píldora del color + sub-etiqueta `ÚLTIMO AL CURSO: HACE Xd / NUNCA` + nota auxiliar `(sí entró a Moodle hace Xd)` cuando aplica.
  - Total curso: barra Tailwind con color por umbral (verde ≥50, ámbar ≥25, rojo <25, gris si no hay nota) + número + enlace "Ver notas".
  - Avisos: contador clicable que abre modal de historial de notificaciones (lee `alumno_notificaciones_log`).
- **Modal Historial**: tabla cronológica de notificaciones con tipo, destinatario, estado (Enviado/Error con motivo).
- **Modal Calificaciones**: tabla con cada actividad — Total destacado en azul, Cuestionario final en verde, columnas Nota/Máx/% y tipo de actividad.

---

<a id="config"></a>
## 8) Configuración

[`config/reportes_moodle.php`](../config/reportes_moodle.php)

Las claves siguen el vocabulario nuevo: `reporte_no_conectados` y `reporte_inactivos` (antes `fase1` y `fase2`).

```php
return [
    'reporte_no_conectados' => [
        'tope_reenvios_alumno'     => env('REPORTES_MOODLE_TOPE_REENVIOS', 3),
        'dias_disparo_alumno'      => [3, 6, 9],
        'cron_alumno_hora'         => '10:00',
        'cron_tutor_hora_lunes'    => '09:00',
        'incluir_tutores_externos' => true,
    ],
    'reporte_inactivos' => [
        'umbral_inactividad_dias' => env('REPORTES_MOODLE_UMBRAL_INACTIVIDAD', 3),
        'umbral_aprobado_puntos'  => env('REPORTES_MOODLE_UMBRAL_APROBADO', 50),
        'throttle_horas'          => env('REPORTES_MOODLE_INACTIVO_THROTTLE_HORAS', 72),
        'cron_alumno_hora'        => '10:15',
    ],
    'snapshot' => [
        'cron_hora'        => '02:00',
        'reintentos'       => 2,
        'lote_lastaccess'  => 50,
    ],
    'moodle' => [
        'categorias_excluidas' => ['Repaso', 'Foros'],
    ],
    'copia_admin_email' => env('REPORTES_MOODLE_COPIA_ADMIN', 'administracion@webcurso.es'),
];
```

> `moodle.categorias_excluidas`: nombres de categorías Moodle cuyos cursos se ignoran en todos los Reportes. Se resuelven a IDs dinámicamente vía `core_course_get_categories` cuando se ejecuta el snapshot.

---

<a id="moodle-ws"></a>
## 9) Pre-requisito: funciones webservice de Moodle

Para que el snapshot capture notas y completion, el token `paneldesarrollo` necesita acceso a estas 18 funciones (las 14 originales + 4 añadidas el 2026-05-07):

```
✅ core_course_get_courses
✅ core_course_get_courses_by_field
✅ core_course_search_courses
✅ core_enrol_get_users_courses
✅ core_enrol_get_enrolled_users           ← añadida
✅ core_completion_get_course_completion_status   ← añadida
✅ core_group_add_group_members
✅ core_group_create_groups
✅ core_user_create_users
✅ core_user_get_user_preferences
✅ core_user_get_users
✅ core_user_get_users_by_field
✅ core_user_update_users
✅ core_webservice_get_site_info
✅ enrol_manual_enrol_users
✅ gradereport_user_get_grades_table
✅ gradereport_user_get_grade_items        ← añadida
✅ mod_quiz_get_user_attempts              ← añadida
```

Se habilitan en *Site administration → Plugins → Web services → External services → Funciones* en el servicio externo asociado al token de `paneldesarrollo`.

Verificación rápida desde Sail:

```php
$svc = new class extends \Modules\Moodle\Services\MoodleService {
    public function callPublic(string $fn, array $p = []): array { return $this->call($fn, $p); }
};
$info = $svc->callPublic('core_webservice_get_site_info', []);
echo "Total: " . count($info['functions']) . "\n";
```

Debe devolver 18 (o más, si en el futuro se habilitan otras).

---

<a id="verificacion"></a>
## 10) Verificación end-to-end

1. **Migrar**: `./vendor/bin/sail artisan migrate` — crea las 3 tablas / extiende la 4ª.
2. **Snapshot**: `./vendor/bin/sail artisan reportes-moodle:snapshot` — debe procesar los alumnos de grupos activos y poblar las dos tablas.
3. **Vista**: navegar a `/webcurso/reportes-moodle` con sesión admin. Refrescar con Ctrl+F5 después de cualquier rebuild de Vite.
4. **Refresh manual**: botón "↻ Refrescar todos ahora" en el header → el snapshotter se ejecuta con `fuente=manual`.
5. **Crons en seco**:
   - `./vendor/bin/sail artisan reportes-moodle:notificar-no-conectados --dry-run`
   - `./vendor/bin/sail artisan reportes-moodle:notificar-inactivos --dry-run`
   - `./vendor/bin/sail artisan reportes-moodle:notificar-tutores --dry-run`
6. **Schedule list**: `./vendor/bin/sail artisan schedule:list | grep reportes-moodle` debe mostrar 4 entradas.
7. **Mailpit**: para previsualizar cómo recibe el alumno el email, ver [`docs/Reportes_moodle_2.docx`](Reportes_moodle_2.docx) y enviar pruebas con scripts ad-hoc (ej. recreando `Mail::mailer('moodle')->to(...)` en un `php -r`).
8. **Tests**: `./vendor/bin/sail php artisan test --filter "MoodleReportingServiceTest|DiasDisparoTest"` — deben pasar 9/9.

---

<a id="archivos"></a>
## 11) Resumen de archivos creados/modificados

### Nuevos

**Migraciones**
- `database/migrations/2026_05_06_000001_create_alumno_progreso_moodle_table.php`
- `database/migrations/2026_05_06_000002_create_alumno_notificaciones_log_table.php`
- `database/migrations/2026_05_07_000001_add_inactividad_y_notas_to_alumno_progreso_moodle.php`
- `database/migrations/2026_05_07_000002_create_alumno_calificaciones_moodle_table.php`

**Modelos**
- `app/Models/AlumnoProgresoMoodle.php`
- `app/Models/AlumnoNotificacionLog.php`
- `app/Models/AlumnoCalificacionMoodle.php`
- `app/Models/GrupoFormativoAlumno.php`

**Servicios**
- `app/Services/Webcurso/MoodleReportingService.php`
- `app/Services/Webcurso/AlumnoProgresoSnapshotter.php`

**Comandos**
- `app/Console/Commands/ReportesMoodle/SnapshotProgresoCommand.php`
- `app/Console/Commands/ReportesMoodle/NotificarNoConectadosCommand.php`
- `app/Console/Commands/ReportesMoodle/NotificarInactivosCommand.php`
- `app/Console/Commands/ReportesMoodle/NotificarTutoresCommand.php`

**Mailables y plantillas**
- `app/Mail/AlumnoNoConectadoMail.php` + `resources/views/emails/alumno-no-conectado.blade.php`
- `app/Mail/AlumnoInactivoMail.php` + `resources/views/emails/alumno-inactivo.blade.php`
- `app/Mail/TutorReporteSemanalMail.php` + `resources/views/emails/tutor-reporte-semanal.blade.php`

**UI**
- `app/Livewire/Webcurso/ReportesMoodleIndex.php`
- `resources/views/livewire/webcurso/reportes-moodle-index.blade.php`

**Config**
- `config/reportes_moodle.php`

**Tests**
- `tests/Unit/Webcurso/MoodleReportingServiceTest.php` (5 tests)
- `tests/Unit/Webcurso/DiasDisparoTest.php` (4 tests)

### Modificados

- `Modules/Moodle/Services/MoodleService.php` — `getUsersByIds`, `getUserGradeItems`.
- `app/Models/Alumno.php` — relaciones `progresoMoodle()`, `notificacionesReportes()`.
- `routes/webcurso.php` — ruta `/webcurso/reportes-moodle`.
- `routes/console.php` — 4 schedules.
- `resources/views/components/sidebar-layout.blade.php` — enlace al menú lateral.

### Eliminados (refactor de Fase 2)

- `app/Mail/TutorListadoNoConectadosMail.php`
- `resources/views/emails/tutor-listado-no-conectados.blade.php`

---

<a id="pendiente"></a>
## 12) Estado final del módulo

Todas las Fases definidas en el documento original más el **Reporte 7** añadido están **entregadas y verificadas**. El módulo está completo. No hay trabajo pendiente.

| # | Reporte | Cron de notificación | Throttle / tope |
|---|---|---|---|
| 1 | No conectados | `reportes-moodle:notificar-no-conectados` · diario 10:00 | Días [3, 6, 9] · tope 3 |
| 2 | Inactivos | `reportes-moodle:notificar-inactivos` · diario 10:15 | 72h · sin tope |
| 3 | Riesgo crítico | `reportes-moodle:notificar-riesgo-critico` · diario 10:30 | 168h (semanal) · sin tope |
| 4 | Pre-cierre | `reportes-moodle:notificar-pre-cierre` · diario 10:45 | 23h · sin tope (max 3 en 72h) |
| 5 | Apto sin examen | `reportes-moodle:notificar-apto-sin-examen` · diario 11:00 | 168h (semanal) · sin tope |
| 6 | Aprobado / Finalizado | `reportes-moodle:notificar-apto` · diario 11:15 | 1 vez por (alumno, grupo) |
| 7 | No aptos / Reinicios | `reportes-moodle:detectar-no-aptos` · 01:30 + `notificar-no-aptos` · 11:30 | 168h · max 4 en 30 días |
| — | Email semanal tutor | `reportes-moodle:notificar-tutores` · lunes 09:00 | Consolidación R1+R2+R3+R4+R5+R6 |

### Cascada de prioridad entre emails al alumno

Si un alumno cumple varios Reportes simultáneamente, **solo recibe el email del Reporte con mayor prioridad** (los demás crones lo excluyen explícitamente):

```
🟧 R4 Pre-cierre       (máxima)
🟡🟠 R1 No conectados  (excluye pre_cierre)
🔴 R3 Riesgo crítico   (excluye pre_cierre)
🟡 R5 Apto sin examen  (excluye pre_cierre)
🟣 R2 Inactivo         (no requiere exclusión adicional)
🟢 R6 Aprobado         (estado terminal)
🟥 R7 No apto          (estado terminal post-cierre)
```

**Visualización**: en la tabla del dashboard, el badge primario refleja el estado más fundamental (R1 → R4 → R3 → R5 → R2). Si un alumno cumple R1 Y otro estado (p.ej. R4), aparece con badge "No entró al curso" + sub-badge gris "+ Pre-cierre" para que el admin sepa que también está en R4.

---

<a id="cambios-2026-05-13"></a>
## 13) Cambios del 2026-05-13 (refactor de vocabulario + dashboard híbrido)

Tras Fases 1 y 2, se hizo un refactor para alinear el código con el vocabulario correcto y reorganizar la UI.

### 13.1 Vocabulario alineado

| Antes | Ahora | Por qué |
|---|---|---|
| "Fase 1" como filtro de UI | "Reporte 1 – No conectados" | Fase = etapa de desarrollo, no es un atributo del alumno |
| "Fase 2" como filtro de UI | "Reporte 2 – Inactivos" | Idem |
| `config.fase1.*` | `config.reporte_no_conectados.*` | Misma razón |
| `config.fase2.*` | `config.reporte_inactivos.*` | Misma razón |
| Propiedad Livewire `$color` | `$reporte` | Es selector de Reporte, no de color |
| Dropdown UI "Color" | Dropdown UI "Reporte" | Idem |

### 13.2 Unificación de "No conectados"

Antes había **dos opciones separadas** en el filtro: "Nunca entró al sitio" y "No entró al curso". Eran sub-estados de un mismo Reporte.

Ahora hay **un único Reporte "No conectados"** que engloba ambos. Dentro de la tabla siguen apareciendo los dos badges con sus colores (amarillo y naranja), pero la KPI es una sola.

### 13.3 Dashboard híbrido con KPIs clicables

La pantalla `/webcurso/reportes-moodle` actúa como **dashboard general**. Las 5 KPIs son botones:

| KPI | Color | Click |
|---|---|---|
| Total en curso | azul | Vuelve al dashboard (Reporte = todos) |
| No conectados | naranja | Abre Reporte 1 (filtra `nunca_entro_curso=true`) |
| Inactivos >3 días | indigo | Abre Reporte 2 (filtra `inactivo=true`) |
| Conectados | verde | Filtra alumnos sanos |
| % en riesgo | rojo | Métrica visual, no clicable |

El estado seleccionado se refleja con un `ring` alrededor del card.

### 13.4 Refinamiento de la regla "Inactivo"

La regla anterior mezclaba estado con acción. Se separó:

- **Estado "Inactivo"** (lo que aparece en UI, badge indigo, KPI): `lastaccess_curso > 0` **AND** `dias_inactivo > 3`. Independiente de si aprobó o no.
- **Email de rescate al alumno** (cron `notificar-inactivos`): solo se envía si `inactivo = true` **AND** `aprobado = false`. Si ya aprobó, está inactivo pero no recibe insistencia.
- **Email semanal al tutor** (cron `notificar-tutores`): el listado de inactivos solo incluye `aprobado = false`. La plantilla añade una columna "Aprobado" preparada para futuro (cuando se incluyan aprobados también).

Nuevos campos en `alumno_progreso_moodle`:
- `aprobado` (bool) — `nota_total >= 50` AND `cuestionario_final_realizado`
- `cuestionario_final_realizado` (bool) — `is_final_quiz` tiene nota válida

### 13.5 Exclusión de categorías Moodle

Implementado el 2026-05-14 una vez habilitada `core_course_get_categories` en el token. El snapshotter resuelve dinámicamente los nombres a IDs **incluyendo todos los descendientes** (subcategorías) mediante el campo `path` de Moodle. Configurable en `config/reportes_moodle.php`:

```php
'moodle' => [
    'categorias_excluidas' => ['Repaso', 'Foros', 'Foros Dominio de lo Aprendido'],
],
```

Si en el futuro se crea "Repaso 2027" como subcategoría de "Repaso", queda excluida automáticamente sin tocar el config.

### 13.6 Comandos artisan actualizados

- `reportes-moodle:snapshot` — sin cambios funcionales, ahora también persiste `aprobado` y `cuestionario_final_realizado`.
- `reportes-moodle:notificar-inactivos` — filtra `inactivo=true AND aprobado=false`. Mensaje cambiado a "No hay alumnos inactivos pendientes de rescate hoy" cuando vacío.
- `reportes-moodle:notificar-tutores` — los inactivos del listado se filtran a `aprobado=false`. La plantilla del email añade columna "Aprobado".

### 13.7 Archivos tocados el 2026-05-13

- `config/reportes_moodle.php` — renombrado de claves + nuevas keys
- `app/Models/AlumnoProgresoMoodle.php` — nuevos casts y fillable
- `app/Services/Webcurso/AlumnoProgresoSnapshotter.php` — separación estado/acción
- `app/Console/Commands/ReportesMoodle/Notificar*Command.php` — uso de nuevas claves y filtro `aprobado=false`
- `app/Livewire/Webcurso/ReportesMoodleIndex.php` — `$color`→`$reporte`, KPIs clicables, KPIs consolidadas
- `resources/views/livewire/webcurso/reportes-moodle-index.blade.php` — UI dashboard híbrido
- `resources/views/emails/tutor-reporte-semanal.blade.php` — columna "Aprobado"
- `resources/views/emails/alumno-no-conectado.blade.php` — clave de config renombrada
- `routes/console.php` — claves de config renombradas
- `database/migrations/2026_05_13_000001_add_aprobado_y_examen_to_alumno_progreso_moodle.php`

---

<a id="cambios-2026-05-14"></a>
## 14) Cambios del 2026-05-14 — Reportes 3 al 7 + cierre del módulo

En una sola jornada se entregaron los 5 Reportes restantes (R3 a R7), cerrando el módulo.

### 14.1 Reporte 3 — Riesgo crítico

- **Regla**: entró al curso AND `nota_total < 50` AND `pct_tiempo_transcurrido >= 50%`. Excluye alumnos que nunca entraron (su problema es R1, no R3).
- **Columna nueva en snapshot**: `riesgo_critico` (bool) + `pct_tiempo_transcurrido` (decimal).
- **Email semanal** (throttle 168h, sin tope) con tono rojo de urgencia. Asunto "⚠ Te quedan X días y aún no llegas a 50 pts".
- **Migraciones**: `2026_05_13_000002_add_riesgo_critico_to_alumno_progreso_moodle.php`.

### 14.2 Reporte 4 — Pre-cierre

- **Regla**: `<=72h antes de fecha_fin` AND `cuestionario_final_realizado = false` (independiente de nota).
- **Prioridad MÁXIMA**: si está en R4, ni R1 ni R3 ni R5 envían email. Solo R4.
- **Email diario** (throttle 23h, sin tope práctico — solo 3 emails en 72h).
- **Asunto urgente**: "🚨 ÚLTIMO AVISO: tu curso cierra en Xh".
- **Fix de timezone**: `calcularHorasHastaFin` ahora re-anchora la fecha en Madrid antes de `endOfDay`.

### 14.3 Reporte 5 — Apto sin examen

- **Regla**: `nota_total >= 50` AND `cuestionario_final_realizado = false`. Complementario a R3.
- **Email semanal** con tono POSITIVO ("ya tienes los 50 puntos, solo falta el cuestionario"). Color amarillo (`#ca8a04`).
- **R4 tiene prioridad**: si entra en Pre-cierre, R5 no envía.

### 14.4 Reporte 6 — Aprobado / Finalizado con éxito

- **Regla**: `aprobado = true` (= nota>=50 AND cuestionario realizado). **No requiere columna nueva** — usa el flag existente.
- **Email ÚNICO** de FELICITACIÓN al detectar la aprobación. El comando verifica que no exista log previo de tipo `alumno_apto` para ese (alumno, grupo).
- **Tono celebratorio**, color verde (`#059669`). Asunto "🎉 ¡Has aprobado [curso]!".
- **Sección en email del tutor**: "Aprobados — éxitos" al final del email semanal.

### 14.5 Reporte 7 — No aptos / Reinicios (sub-proyecto, fuera de las 6 originales)

Es el más complejo porque introduce mecánica de **reinicios de curso**.

#### Arquitectura SEPARADA del snapshot

- El snapshot diario solo procesa cursos activos. Para R7 se creó **infraestructura paralela**:
  - **Tabla `alumno_no_aptos`** — registro PERMANENTE de cada suspensión. Una fila por (alumno, grupo). Estados del ciclo de reinicio: `pendiente` → `ofrecido` → `aceptado` / `reiniciado` / `caducado` / `rechazado`.
  - **Tabla `alumno_reinicio_ofrecimientos`** — auditoría de cada email enviado (1, 2, 3, 4).
- Comando **`detectar-no-aptos`** (diario 01:30) examina cursos cuyo `fecha_fin < hoy` y el último snapshot del alumno. Si nota<50 + sin cuestionario → registra en `alumno_no_aptos` (idempotente).

#### Email de ofrecimiento

- Asunto: "Una segunda oportunidad para terminar [curso]".
- Color rojo oscuro (`#7f1d1d`).
- **Botón `mailto:` al admin** con asunto `REINICIO-{id}` y body predefinido — el alumno solo pulsa el botón, su cliente de email se abre con un mensaje listo para enviar. **No requiere endpoint web ni autenticación**.
- Cadencia: **semanal** (throttle 168h), máximo **4 ofrecimientos en 30 días**. Después se marca `caducado` automáticamente.

#### Cierre del ciclo

- En la UI hay una **vista dedicada** cuando se filtra "No aptos": tabla con columnas específicas (fin curso, nota final, # ofrecimientos, estado).
- **Botón "✓ Marcar reiniciado"** (con `wire:confirm`) cuando el admin crea el nuevo grupo formativo. Detiene los emails y registra fecha + user_id.
- **Botón "✗ Rechazar"** para descartar reinicio (alumno no quiere, decisión administrativa).

#### Tope de reinicios

- `max_reinicios_alumno = 0` (sin tope por defecto, configurable vía `REPORTES_MOODLE_NO_APTOS_MAX_REINICIOS`).

### 14.6 Sub-badges en la tabla (mejora de UX para R1 + R4)

Cuando un alumno cumple R1 (no entró) Y R4 (pre-cierre) a la vez, el badge primario mantiene "No entró al curso" / "Nunca al sitio" y se añade un **sub-badge gris pequeño "+ Pre-cierre <72h"** debajo. Lo mismo para R3, R2 cuando se combinan con R1. Esto permite que el alumno aparezca en TODOS los filtros que le aplican (KPIs y dropdown), sin perder la identidad visual del problema fundamental.

### 14.7 Refactor del email semanal del tutor

`TutorReporteSemanalMail` ahora recibe 6 arrays: `noConectados`, `inactivos`, `riesgoCritico`, `preCierre`, `aptoSinExamen`, `aprobados`. Cada uno renderiza una sección con color y prioridad propia. **R7 NO entra en el email del tutor** porque la responsabilidad del reinicio es del admin, no del tutor.

### 14.8 Archivos creados/modificados el 2026-05-14

**Migraciones**:
- `2026_05_13_000002_add_riesgo_critico_to_alumno_progreso_moodle.php`
- `2026_05_14_000001_add_pre_cierre_to_alumno_progreso_moodle.php`
- `2026_05_14_000002_add_apto_sin_examen_to_alumno_progreso_moodle.php`
- `2026_05_14_000003_create_alumno_no_aptos_table.php`
- `2026_05_14_000004_create_alumno_reinicio_ofrecimientos_table.php`

**Modelos**:
- `app/Models/AlumnoNoApto.php`
- `app/Models/AlumnoReinicioOfrecimiento.php`
- (`AlumnoProgresoMoodle.php` extendido con flags + scopes nuevos)

**Mailables**:
- `app/Mail/AlumnoRiesgoCriticoMail.php` + plantilla
- `app/Mail/AlumnoPreCierreMail.php` + plantilla
- `app/Mail/AlumnoAptoSinExamenMail.php` + plantilla
- `app/Mail/AlumnoAptoMail.php` + plantilla
- `app/Mail/AlumnoNoAptoMail.php` + plantilla (botón mailto)
- (`TutorReporteSemanalMail.php` actualizado para 6 secciones)

**Comandos**:
- `app/Console/Commands/ReportesMoodle/NotificarRiesgoCriticoCommand.php`
- `app/Console/Commands/ReportesMoodle/NotificarPreCierreCommand.php`
- `app/Console/Commands/ReportesMoodle/NotificarAptoSinExamenCommand.php`
- `app/Console/Commands/ReportesMoodle/NotificarAptoCommand.php`
- `app/Console/Commands/ReportesMoodle/DetectarNoAptosCommand.php`
- `app/Console/Commands/ReportesMoodle/NotificarNoAptosCommand.php`

**Config**:
- `config/reportes_moodle.php` — añadidos bloques `reporte_riesgo_critico`, `reporte_pre_cierre`, `reporte_apto_sin_examen`, `reporte_apto`, `reporte_no_aptos`.

**UI**:
- `app/Livewire/Webcurso/ReportesMoodleIndex.php` — 10 KPIs clicables, 9 opciones de Reporte, vista alternativa para R7, métodos `marcarReiniciado` / `marcarRechazado`.
- `resources/views/livewire/webcurso/reportes-moodle-index.blade.php` — badges + sub-badges + leyenda + tabla dedicada No aptos.

**Scheduler**:
- `routes/console.php` — 10 crones registrados en total (`snapshot`, `notificar-no-conectados`, `notificar-inactivos`, `notificar-riesgo-critico`, `notificar-pre-cierre`, `notificar-apto-sin-examen`, `notificar-apto`, `detectar-no-aptos`, `notificar-no-aptos`, `notificar-tutores`).

### 14.9 Verificación end-to-end

- Migraciones aplicadas sin errores (47 totales). Resolución de un bug de nombre de índice MySQL >64 chars en `alumno_reinicio_ofrecimientos`.
- 4 emails de preview enviados a Mailpit con datos simulados (R3, R4, R5, R6) y 1 con datos reales (R7 — greeicy barreto suspendida en Excel 365).
- Comando `detectar-no-aptos` corrió end-to-end y registró correctamente a greeicy.
- `notificar-no-aptos --dry-run` listó el ofrecimiento esperado.
- 9/9 tests Pest pasando.
