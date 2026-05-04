# Migración legacy webcourses2014 + Mejoras bonificados

Documento técnico que recopila las funcionalidades implementadas en mayo de 2026 alrededor de la migración de datos del sistema legacy `webcourses2014` (CodeIgniter antiguo) y las mejoras en el flujo de envío de email de saldo a participantes bonificados.

Fecha: **2026-05-03**.

---

## Índice

1. [⚠ Regla innegociable de seguridad de emails en pruebas](#regla-innegociable)
2. [Migración masiva legacy → Panel](#migracion-legacy)
3. [Historial de cursos legacy en `/webcurso/alumnos`](#historial-cursos-legacy)
4. [Filtros por fechas en AlumnosIndex](#filtros-fechas-alumnos)
5. [Mejoras en el flujo de email de saldo bonificados](#email-saldo-bonificados)
6. [Resumen de archivos creados/modificados](#archivos)
7. [Comandos artisan disponibles](#comandos)
8. [Verificación end-to-end](#verificacion)

---

<a id="regla-innegociable"></a>
## 1) ⚠ Regla innegociable de seguridad de emails en pruebas

**Contexto:** los datos en `/webcurso/participantes-bonificados`, `alumnos.email`, `empresas.email`, etc. son **reales** (personas y empresas con expectativa de privacidad). Durante el desarrollo en local se descubrió que el `.env` apuntaba a `MAIL_HOST=smtp.gmail.com` (Gmail real), por lo que **cualquier comando que enviara correo escapaba al SMTP real** aunque la intención fuera probar.

**Regla aplicada:** en cualquier `APP_ENV != production`, ningún email del sistema puede llegar a destinatarios reales. Todo debe terminar en Mailpit (http://localhost:8025).

### Doble defensa implementada

1. **A nivel global** — [`app/Providers/AppServiceProvider.php`](../app/Providers/AppServiceProvider.php) método `boot()`:
   - **Fuerza el host/puerto SMTP** de TODOS los mailers configurados a Mailpit (`mailpit:1025`), ignorando cualquier `MAIL_HOST=smtp.gmail.com` u otro real
   - **Reescribe el destinatario** con `Mail::alwaysTo(env('MAIL_FORCE_TO', 'pruebas@webcurso.local'))`

2. **A nivel comando** — [`EnviarEmailSaldoBonificados`](../app/Console/Commands/EnviarEmailSaldoBonificados.php):
   - Detecta entorno y, si es non-production, redirige el `To` y vacía CCs reales
   - El estado `modo_prueba=true` se guarda en `bonificado_email_envios` para auditoría

### Variables de entorno

```env
# Activan en cualquier APP_ENV != production
MAIL_FORCE_TO=pruebas@webcurso.local
MAIL_FORCE_HOST=mailpit
MAIL_FORCE_PORT=1025

# Opcional — forzar modo prueba aunque APP_ENV=production
MAIL_FORCE_TESTING=true
```

### Para pasar a producción

1. `APP_ENV=production` en el `.env` del servidor
2. Eliminar/no definir `MAIL_FORCE_*`
3. Confirmar que `MAIL_HOST` apunta al SMTP real correcto (Gmail con app password)
4. La protección queda automáticamente inactiva al detectar `production`

---

<a id="migracion-legacy"></a>
## 2) Migración masiva legacy → Panel

**Contexto:** el sistema antiguo `webcourses2014` (CodeIgniter, BD MySQL en el mismo contenedor) contiene 15,944 registros en `tbl_member` con datos personales útiles (email, teléfono, NISS, nivel de estudios, categoría profesional, grupo cotización TGSS) que el Panel actual necesita para enriquecer alumnos. Antes de esta migración, el comando `alumnos:importar-bonificados` consultaba la BD legacy en vivo cada vez, era manual y tenía baja cobertura.

**Hallazgo decisivo (corrigiendo nota previa errónea):** el formulario antiguo de webcourses2014 cambió de convención según la fecha del registro:

| Tipo de registro | `personal_id` | `nid` | `company` |
|---|---|---|---|
| **Reciente** (caso Juan Sanchez, mem_id 27364) | `44818858J` (NIF persona) | **`B29416393` (CIF)** | `FORZA HORMIGONES, S.L.` |
| **Antiguo** (caso Xavier Vidal, mem_id 5118) | `123456789` (basura) | `123456789` (basura) | **`B66306564` (CIF)** |

Volumetría real tras normalizar (TRIM+UPPER+sin espacios/guiones):
- 3,309 NIFs persona únicos válidos en legacy
- 1,922 CIFs únicos en `nid` con formato CIF
- 340 CIFs únicos en `company` con formato CIF
- **146/189 empresas Panel (77%)** tienen alumnos en legacy
- 309 NIFs persona únicos migrables a `alumnos`

### Tabla `alumnos_legacy_pool`

Migración: [`database/migrations/2026_05_03_000001_create_alumnos_legacy_pool_table.php`](../database/migrations/2026_05_03_000001_create_alumnos_legacy_pool_table.php)

Cache local que reemplaza queries en vivo a `webcourses2014`. Una entrada por NIF persona único.

| Columna | Origen legacy |
|---|---|
| `nif` (UNIQUE) | `tbl_member.personal_id` normalizado |
| `nombre`, `apellido1`, `apellido2` | `first_name`, `last_name` (separado en 2 con limpieza de sufijos `(REPASO)`) |
| `email` | `email` |
| `telefono` | `phone` o fallback `mobile` |
| `niss` | `niis` o fallback `social_security_number` |
| `fecha_nacimiento` | `dob` (parseado en formatos `Y-m-d`, `d/m/Y`, etc.) |
| `nivel_estudios` (1-10) | `level_of_studies` (texto) → mapeo trait |
| `categoria_profesional` (1-5) | `professional_category` → mapeo trait |
| `grupo_cotizacion_tgss` (1-11) | `listed_group` → mapeo trait |
| `legacy_nid` | `nid` original (auditoría) |
| `legacy_company_text` | `company` original (auditoría + fallback fuzzy) |
| `legacy_cif_resuelto` | resultado de la resolución multi-fuente del CIF |
| `source_mem_id` | `mem_id` legacy (trazabilidad) |
| `imported_at` | timestamp |

### Comando `alumnos:migrar-legacy`

Archivo: [`app/Console/Commands/MigrarLegacy.php`](../app/Console/Commands/MigrarLegacy.php)

Snapshot one-shot. Ejecuta 4 fases:

- **Fase A — Pool:** consulta `tbl_member` filtrando por `personal_id` con formato NIF/NIE válido, guarda en `alumnos_legacy_pool` por NIF único (más reciente por `mem_id`)
- **Fase B — Alumnos directos:** resuelve CIF empresa con estrategia multi-fuente y crea/actualiza `alumnos`:
  1. `tbl_member.nid` ↔ `empresas.cif` (registros recientes — caso Juan, ~284 alumnos)
  2. `tbl_member.company` con formato CIF ↔ `empresas.cif` (registros antiguos — caso Xavier)
  3. Fuzzy match `tbl_member.company` ↔ `empresas.razon_social` (~5 alumnos)
- **Fase D — Cursos legacy:** snapshot de `tbl_member_courses` JOIN `tbl_courses` a `alumnos_legacy_cursos` (4,092 entradas, 2,994 NIFs únicos)
- **Fase E — Enriquecimiento acción/grupo:** invoca automáticamente `alumnos:enriquecer-cursos-legacy` (ver sección 3)

**Anti-duplicados:** detecta homónimos (mismo `nombre`+`apellido1`+`empresa_id` con NIF distinto) y enriquece el alumno existente en lugar de crear duplicado. Caso real: Juan Sanchez tiene `74848858G` (FUNDAE actual) y `44818858J` (legacy) — el comando enriquece el primero en vez de crear un segundo.

**Flags:** `--dry-run`, `--force`, `--solo-pool`, `--solo-alumnos`, `--solo-cursos`, `--sin-fuzzy-razon-social`, `--limit=N`

### Trait compartido `LegacyMappings`

Archivo: [`app/Console/Commands/Concerns/LegacyMappings.php`](../app/Console/Commands/Concerns/LegacyMappings.php)

Centraliza mapeos texto→código FUNDAE y normalizaciones reutilizadas por `MigrarLegacy` e `ImportarAlumnosBonificados`:
- `mapearNivelEstudios()`, `mapearCategoriaProfesional()`, `mapearGrupoCotizacion()`
- `separarApellidos()`, `separarNombreCompleto()`
- `normalizarCif()`, `normalizarRazonSocial()`, `normalizarNif()`, `esNifValido()`
- `resolverCifLegacy()` (orden nid → company)

### Refactor de `alumnos:importar-bonificados`

Archivo: [`app/Console/Commands/ImportarAlumnosBonificados.php`](../app/Console/Commands/ImportarAlumnosBonificados.php)

Ya no consulta `webcourses2014` en vivo — todo viene del pool local. Cambios:
- Consulta `AlumnoLegacyPool::where('nif', $nif)`
- **Fallback por nombre+empresa** cuando NIF FUNDAE no coincide con NIF legacy (caso Juan)
- **Alumno mínimo + flag `datos_pendientes=true`** cuando no hay match en pool ni por nombre
- Mantiene flags `--dry-run`, `--force`

### Auto-ejecución tras importar XLS de FUNDAE

[`app/Livewire/Webcurso/ImportarCsv.php`](../app/Livewire/Webcurso/ImportarCsv.php) — tras llamar a `$service->importarParticipantes(...)` invoca automáticamente `Artisan::call('alumnos:importar-bonificados', ['--force' => true])` y muestra el resumen al usuario en la vista.

### Mejoras en `/webcurso/participantes-bonificados`

[`app/Livewire/Webcurso/ParticipantesBonificadosIndex.php`](../app/Livewire/Webcurso/ParticipantesBonificadosIndex.php):
- **Banner amarillo** arriba del listado cuando hay participantes sin email registrado: muestra conteo y botón "Reejecutar enriquecimiento" que invoca `alumnos:importar-bonificados --force`
- **Badge "📧 sin email"** en cada fila sin email
- **Badge "⚠ datos pendientes"** en filas cuyo alumno tenga `datos_pendientes=true`
- **Filtro `filtroSinEmail`** (toggle): muestra solo participantes sin email registrado en alumnos

### Nueva columna en `alumnos`

Migración: [`database/migrations/2026_05_03_000002_add_datos_pendientes_to_alumnos_table.php`](../database/migrations/2026_05_03_000002_add_datos_pendientes_to_alumnos_table.php)

`datos_pendientes` BOOLEAN default `false` indexado. Marca alumnos creados desde XLS sin email/datos legacy disponibles para que el admin los complete manualmente.

---

<a id="historial-cursos-legacy"></a>
## 3) Historial de cursos legacy en `/webcurso/alumnos`

**Contexto:** el modal "Historial" del alumno mostraba grupos del Panel y participaciones FUNDAE actuales, pero no los cursos que ese alumno hizo en el sistema antiguo (webcourses2014). Casos como Marc Font (NIF `47717038L`) tenían historial completo en legacy (curso AutoCAD 2021 Completo+3D) que no se veía en el Panel.

### Tabla `alumnos_legacy_cursos`

Migración: [`database/migrations/2026_05_03_000003_create_alumnos_legacy_cursos_table.php`](../database/migrations/2026_05_03_000003_create_alumnos_legacy_cursos_table.php)
Modelo: [`app/Models/AlumnoLegacyCurso.php`](../app/Models/AlumnoLegacyCurso.php)

Snapshot de `tbl_member_courses` JOIN `tbl_member` JOIN `tbl_courses` indexado por NIF persona. 4,092 entradas, 2,994 NIFs únicos.

| Columna | Origen legacy |
|---|---|
| `nif` (indexed) | `tbl_member.personal_id` (vinculación cross-tabla con `alumnos.nif`) |
| `course_id` | `tbl_member_courses.course_id` |
| `curso_titulo`, `curso_short_name`, `curso_horas` | `tbl_courses.c_title/course_short_name/course_hours` |
| `fecha_inicio`, `fecha_fin` | `tbl_member_courses.c_start_date/c_end_date` |
| `estado_curso` | `c_status` (Running/Completed/Closed/Upcoming) — **NO renderizado en UI**, ver decisión más abajo |
| `resultado` | `c_result` (Pass/Fail/Not Declared) — **NO renderizado en UI** |
| `formation_group_alpha`, `formation_group_number` | acción y grupo FUNDAE (a menudo NULL — se enriquecen vía Fase E) |
| `grupo_id_fundae` | añadido en Fase E desde `grupos.grupo_id` o regex sobre `participantes_bonificados.id_codigo_grupo` |
| `origen_enriquecimiento` | `'grupos_fundae'` o `'participantes_bonificados'` o `NULL` |
| `legacy_company_text`, `legacy_cif_resuelto` | auditoría |
| `source_mc_id` (UNIQUE con `nif`) | trazabilidad |

### Relación en `Alumno`

Añadida en [`app/Models/Alumno.php`](../app/Models/Alumno.php):

```php
public function cursosLegacy(): HasMany {
    return $this->hasMany(AlumnoLegacyCurso::class, 'nif', 'nif');
}
```

Patrón **HasMany cross-tabla por NIF** (igual que `participantesBonificados`), no FK clásica. Permite que un NIF aparezca en `alumnos_legacy_cursos` antes de existir en `alumnos`, y se vincule automáticamente cuando aparezca.

### Comando `alumnos:enriquecer-cursos-legacy`

Archivo: [`app/Console/Commands/EnriquecerCursosLegacy.php`](../app/Console/Commands/EnriquecerCursosLegacy.php)

Rellena `formation_group_alpha`/`number` y `grupo_id_fundae` para cursos legacy que migraron sin esa info. Dos estrategias:

1. **Vía tabla `grupos`** (importada de XLS FUNDAE): JOIN por `empresas.cif`, fechas (inicio/fin) y nombre del alumno en `denominacion`. Toma `codigo_grupo_accion_formativa` (acción), `codigo_grupo` (grupo) y `grupo_id`. Resultado típico: ~87 enriquecidos
2. **Vía `participantes_bonificados`**: cruce por NIF + fechas exactas, parsea `id_codigo_grupo` con regex `/(N) accion/grupo/`. Resultado: ~20 enriquecidos

Auto-ejecución como Fase E dentro de `alumnos:migrar-legacy`. Flags: `--dry-run`, `--force`.

### Vista del modal historial

[`resources/views/livewire/webcurso/alumnos-index.blade.php`](../resources/views/livewire/webcurso/alumnos-index.blade.php) sección "LEGACY · HISTORIAL WEBCOURSES2014":

| Columna | Origen |
|---|---|
| Curso (legacy) | `curso_titulo`, `curso_short_name`, `curso_horas` |
| Acción formativa | `formation_group_alpha` con resolución a `acciones_formativas.numero_accion` del Panel cuando exista (muestra denominación completa) |
| Grupo | Formato `(grupo_id) accion/grupo` — ej. `(75143) 201/1` — con leyenda del origen del enriquecimiento |
| Empresa | `legacy_company_text` |
| Fechas | `fecha_inicio → fecha_fin` |

**Decisión de diseño:** las columnas `Estado` y `Resultado` del legacy (`Running`/`Completed`/`Pass`/`Fail`) **NO se renderizan** porque pertenecen al modelo legacy y rompen la consistencia con el modelo actual (que usa `comunicado`/`en_curso` para estado de grupo y `pendiente`/`matriculado` para Moodle). Los campos siguen guardándose en BD por si se necesitan en otro contexto.

### Badge y filtro en la lista

[`AlumnosIndex.php`](../app/Livewire/Webcurso/AlumnosIndex.php):
- Badge violeta **"N legacy"** en columna Grupos cuando el alumno tiene cursos legacy
- Filtro Tipo: nueva opción **"Con historial legacy"** que filtra por `whereHas('cursosLegacy')`
- Botón "Historial" disponible cuando hay datos en cualquiera de las 4 fuentes (grupos formativos, participantes bonificados, autónomos, legacy)

---

<a id="filtros-fechas-alumnos"></a>
## 4) Filtros por fechas en AlumnosIndex

[`app/Livewire/Webcurso/AlumnosIndex.php`](../app/Livewire/Webcurso/AlumnosIndex.php) — tres filtros nuevos:

| Filtro | Comportamiento |
|---|---|
| **Año del curso** | Dropdown poblado dinámicamente con años disponibles (UNION de `YEAR(fecha_inicio)` de las 4 fuentes) |
| **Desde** | Date picker — `fecha_inicio >= valor` |
| **Hasta** | Date picker — `fecha_inicio <= valor` |

Buscan en las **4 fuentes simultáneamente** (`gruposFormativos`, `participantesBonificados`, `matriculasAutonomas`, `cursosLegacy`) via `whereHas` con OR. Combinables (año + rango se aplican como AND a cada subquery). Persisten en URL (queryString) y se reinician con "Limpiar filtros".

---

<a id="email-saldo-bonificados"></a>
## 5) Mejoras en el flujo de email de saldo bonificados

### Funcionalidades nuevas

1. **CC al `empresas.email`** (administrador legal de la empresa correspondiente al CIF del participante) en cada email enviado
2. **CC fijo a `webcurso@webcurso.es`** (configurable via `CANDIDATOS_BONIFICADOS_CC_ADMIN`) como copia administrativa interna
3. **Tracking histórico** de cada envío en nueva tabla
4. **Vista mejorada** con próxima fecha programada y última fecha de envío por participante

### Tabla `bonificado_email_envios`

Migración: [`database/migrations/2026_05_03_000005_create_bonificado_email_envios_table.php`](../database/migrations/2026_05_03_000005_create_bonificado_email_envios_table.php)
Modelo: [`app/Models/BonificadoEmailEnvio.php`](../app/Models/BonificadoEmailEnvio.php)

| Columna | Notas |
|---|---|
| `nif`, `cif` (indexed) | participante y empresa |
| `email_destinatario` | email real del alumno (auditoría) |
| `email_cc_empresa` | `empresas.email` que se aplicó (o `NULL` si vacío) |
| `email_cc_admin` | `webcurso@webcurso.es` (config) |
| `nombre_participante`, `razon_social` | snapshots |
| `saldo_enviado` | snapshot del crédito disponible en el momento |
| `metodo` ENUM | `cron` o `manual` (según se invoque desde scheduler o desde botón UI) |
| `modo_prueba` BOOLEAN | TRUE si se generó en non-production (regla innegociable) |
| `enviado_at` | timestamp |

Índice compuesto `(nif, cif, enviado_at DESC)` para lookups rápidos del último envío.

### Modificación de `SaldoBonificadoMensualMail`

[`app/Mail/SaldoBonificadoMensualMail.php`](../app/Mail/SaldoBonificadoMensualMail.php) — constructor acepta nuevo parámetro `array $emailsCc = []` (lista de copias). Reemplaza los CCs hardcoded anteriores por los que pase el comando según contexto.

### Modificación del comando

[`EnviarEmailSaldoBonificados`](../app/Console/Commands/EnviarEmailSaldoBonificados.php):
- Nuevo flag `--manual` para distinguir invocaciones desde botón UI vs scheduler
- Construye array de CCs por participante: `array_filter([$empresa->email, config('candidatos.email_saldo_bonificados.cc_admin')])`
- Tras envío exitoso crea registro en `bonificado_email_envios`
- En `--dry-run`: NO crea registro
- En non-production: ignora CCs reales y redirige `To` a `pruebas@webcurso.local` (regla innegociable)
- Respeta exclusiones (`bonificado_email_exclusiones`) igual que antes

### Próxima fecha programada y últimos envíos

[`ParticipantesBonificadosIndex`](../app/Livewire/Webcurso/ParticipantesBonificadosIndex.php):

Helper `calcularProximoEnvio()` calcula la próxima ocurrencia del cron según `config('candidatos.email_saldo_bonificados.frecuencia')`:
- `monthly`: próximo día N a hora H
- `weekly`: próximo lunes a hora H
- `biweekly`: próximo día 1 o 15

Devuelve `Carbon` en `Europe/Madrid` o `null` si está desactivado.

En `render()` carga `BonificadoEmailEnvio::whereIn('nif', $nifs)->groupBy('nif')->selectRaw('nif, MAX(enviado_at) as ultimo')` para los participantes de la página actual.

### Vista

[`resources/views/livewire/webcurso/participantes-bonificados-index.blade.php`](../resources/views/livewire/webcurso/participantes-bonificados-index.blade.php):
- Panel "Email mensual de saldo": añade líneas "📅 Próximo envío programado" y "📨 Copias enviadas a"
- Banner amarillo "🧪 MODO PRUEBA" en non-production
- Nueva columna **"Último envío"** en cada fila con fecha y "hace X tiempo" (o "Nunca")

### Configuración

[`config/candidatos.php`](../config/candidatos.php) sección `email_saldo_bonificados`:

```php
'cc_admin' => env('CANDIDATOS_BONIFICADOS_CC_ADMIN', 'webcurso@webcurso.es'),
```

Variables `.env`:

```env
BONIFICADOS_EMAIL_ACTIVO=true
BONIFICADOS_EMAIL_FRECUENCIA=monthly
BONIFICADOS_EMAIL_DIA=1
BONIFICADOS_EMAIL_HORA=10:00
BONIFICADOS_EMAIL_ERRORES=
CANDIDATOS_BONIFICADOS_CC_ADMIN=webcurso@webcurso.es
```

---

<a id="archivos"></a>
## 6) Resumen de archivos creados/modificados

### Migraciones nuevas

```
database/migrations/2026_05_03_000001_create_alumnos_legacy_pool_table.php
database/migrations/2026_05_03_000002_add_datos_pendientes_to_alumnos_table.php
database/migrations/2026_05_03_000003_create_alumnos_legacy_cursos_table.php
database/migrations/2026_05_03_000004_add_grupo_id_fundae_to_alumnos_legacy_cursos.php
database/migrations/2026_05_03_000005_create_bonificado_email_envios_table.php
```

### Modelos nuevos

```
app/Models/AlumnoLegacyPool.php
app/Models/AlumnoLegacyCurso.php
app/Models/BonificadoEmailEnvio.php
```

### Comandos nuevos

```
app/Console/Commands/MigrarLegacy.php
app/Console/Commands/EnriquecerCursosLegacy.php
app/Console/Commands/Concerns/LegacyMappings.php   (trait)
```

### Modelos modificados

- [`app/Models/Alumno.php`](../app/Models/Alumno.php): añadido `datos_pendientes` a `$fillable`/`$casts`; nueva relación `cursosLegacy()`

### Comandos modificados

- [`app/Console/Commands/ImportarAlumnosBonificados.php`](../app/Console/Commands/ImportarAlumnosBonificados.php): refactor completo para consultar pool local + fallback nombre+empresa + alumno mínimo
- [`app/Console/Commands/EnviarEmailSaldoBonificados.php`](../app/Console/Commands/EnviarEmailSaldoBonificados.php): CCs + tracking + flag `--manual` + bloqueo de modo prueba

### Componentes Livewire modificados

- [`app/Livewire/Webcurso/ImportarCsv.php`](../app/Livewire/Webcurso/ImportarCsv.php): auto-trigger de `alumnos:importar-bonificados` tras importar XLS de participantes
- [`app/Livewire/Webcurso/ParticipantesBonificadosIndex.php`](../app/Livewire/Webcurso/ParticipantesBonificadosIndex.php): banner sin email + badges + filtro + próxima fecha + últimos envíos + flag manual
- [`app/Livewire/Webcurso/AlumnosIndex.php`](../app/Livewire/Webcurso/AlumnosIndex.php): badge legacy + filtro Tipo "legacy" + carga `cursosLegacy` y `accionesPorNumero` para modal + filtros año/desde/hasta

### Mailables modificados

- [`app/Mail/SaldoBonificadoMensualMail.php`](../app/Mail/SaldoBonificadoMensualMail.php): constructor con array de CCs en lugar de hardcoded

### Vistas Blade modificadas

- [`resources/views/livewire/webcurso/alumnos-index.blade.php`](../resources/views/livewire/webcurso/alumnos-index.blade.php): badge legacy + filtro tipo + filtros fechas + sección historial legacy en modal
- [`resources/views/livewire/webcurso/participantes-bonificados-index.blade.php`](../resources/views/livewire/webcurso/participantes-bonificados-index.blade.php): banner sin email + badges + filtro + próxima fecha + columna último envío + aviso modo prueba
- [`resources/views/livewire/webcurso/importar-csv.blade.php`](../resources/views/livewire/webcurso/importar-csv.blade.php): muestra resumen del enriquecimiento automático

### Configuración

- [`app/Providers/AppServiceProvider.php`](../app/Providers/AppServiceProvider.php): red de seguridad SMTP global + `Mail::alwaysTo` en non-production
- [`config/candidatos.php`](../config/candidatos.php): nueva entrada `cc_admin`
- [`.env.example`](../.env.example): documentadas variables `MAIL_FORCE_TO`, `MAIL_FORCE_HOST`, `MAIL_FORCE_PORT`, `MAIL_FORCE_TESTING` (regla innegociable) y `BONIFICADOS_*`, `CANDIDATOS_BONIFICADOS_CC_ADMIN`

### Documentación de dominio actualizada

- `.claude/rules/estado-actual-panel.md`: nuevas secciones "Migración masiva legacy → pool local", "Vista Participantes Bonificados — banner + filtros", "Historial de cursos legacy", "Enriquecimiento acción/grupo cursos legacy", "AlumnosIndex — filtros por fechas"
- `.claude/rules/arquitectura-tecnica.md`: añadidas entidades `AlumnoLegacyPool`, `AlumnoLegacyCurso`

---

<a id="comandos"></a>
## 7) Comandos artisan disponibles

```bash
# Migración one-shot del legacy (4 fases)
./vendor/bin/sail artisan alumnos:migrar-legacy
./vendor/bin/sail artisan alumnos:migrar-legacy --dry-run
./vendor/bin/sail artisan alumnos:migrar-legacy --solo-pool
./vendor/bin/sail artisan alumnos:migrar-legacy --solo-alumnos
./vendor/bin/sail artisan alumnos:migrar-legacy --solo-cursos
./vendor/bin/sail artisan alumnos:migrar-legacy --sin-fuzzy-razon-social
./vendor/bin/sail artisan alumnos:migrar-legacy --limit=50

# Enriquecer acción/grupo de cursos legacy desde grupos FUNDAE + participantes bonificados
./vendor/bin/sail artisan alumnos:enriquecer-cursos-legacy
./vendor/bin/sail artisan alumnos:enriquecer-cursos-legacy --dry-run
./vendor/bin/sail artisan alumnos:enriquecer-cursos-legacy --force

# Enriquecer alumnos desde participantes bonificados + pool local (auto-trigger tras XLS)
./vendor/bin/sail artisan alumnos:importar-bonificados
./vendor/bin/sail artisan alumnos:importar-bonificados --dry-run
./vendor/bin/sail artisan alumnos:importar-bonificados --force

# Cron mensual de email de saldo a participantes
./vendor/bin/sail artisan bonificados:enviar-email-saldo
./vendor/bin/sail artisan bonificados:enviar-email-saldo --dry-run
./vendor/bin/sail artisan bonificados:enviar-email-saldo --manual
```

---

<a id="verificacion"></a>
## 8) Verificación end-to-end

### Migración legacy

```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan alumnos:migrar-legacy --dry-run
./vendor/bin/sail artisan alumnos:migrar-legacy
```

Resultado esperado:
- ~3,030 entradas en `alumnos_legacy_pool`
- ~289 alumnos nuevos creados (~284 vía nid → cif + ~5 vía fuzzy razón social)
- ~2,694 NIFs en pool sin empresa derivable (quedan disponibles cuando aparezca su CIF en futuros XLS)
- 4,092 entradas en `alumnos_legacy_cursos` (2,994 NIFs únicos)
- ~107 cursos enriquecidos con acción/grupo

Verificar caso fidelizado (Juan Sanchez):
```sql
SELECT id, nif, email, telefono, empresa_id FROM alumnos
WHERE email LIKE '%onzaga%' OR nif IN ('44818858J','74848858G');
```

### Subida de XLS test

1. Subir XLS de participantes en `/webcurso/importar-csv`
2. Verificar resumen del enriquecimiento automático
3. Recargar `/webcurso/participantes-bonificados`: NIFs deben tener email
4. Casos de NIF nuevo no en pool: alumno mínimo creado con `datos_pendientes=true` y badge ⚠

### Email de saldo (modo prueba)

```bash
./vendor/bin/sail artisan bonificados:enviar-email-saldo --dry-run
./vendor/bin/sail artisan bonificados:enviar-email-saldo
```

Confirmar:
1. **Mailpit (http://localhost:8025) recibe TODOS los correos** (`SMTPAccepted` debe igualar emails enviados)
2. Todos los `To` apuntan a `pruebas@webcurso.local`, CCs vacíos
3. Tabla `bonificado_email_envios` guarda los datos reales con `modo_prueba=1`
4. Vista de participantes muestra "Próximo envío programado" y columna "Último envío"
5. Botón "Enviar ahora" registra `metodo='manual'`

### Antes de pasar a producción

- Verificar `APP_ENV=production`
- Eliminar variables `MAIL_FORCE_*` del `.env`
- Confirmar `MAIL_HOST` apunta al SMTP real
- Test con un participante de baja sensibilidad: confirmar que el correo llega al destinatario y al CC del `empresas.email` y a `webcurso@webcurso.es`
