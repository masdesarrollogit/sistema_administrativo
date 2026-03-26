# API Moodle — Referencia

Estado: **FUNCIONAL**. Integrado con Moodle LMS en `http://moodle_app` (interno Docker).

## Configuracion

```php
// config/moodle.php
'url'        => env('MOODLE_URL'),         // URL interna Docker: http://moodle_app
'token'      => env('MOODLE_TOKEN'),       // Token de webservice Moodle
'public_url' => env('MOODLE_PUBLIC_URL', 'https://aula.1curso.com'),  // URL publica para emails al alumno
'mail_from'  => env('MOODLE_MAIL_FROM', 'info@aula.1curso.com'),      // From de emails de credenciales
```

**Nota de red Docker:** La conexion interna usa `MOODLE_URL=http://moodle_app` pero se envia el header `Host: localhost:8080` para que Moodle acepte la peticion. Esto se gestiona automaticamente en `MoodleService`.

**URL publica:** Solo se usa en los emails enviados a los alumnos (enlace directo al curso).

## Autenticacion

Moodle usa tokens de webservice. El token se pasa como parametro `wstoken` en cada peticion REST.

```
POST {MOODLE_URL}/webservice/rest/server.php
Content-Type: application/x-www-form-urlencoded

wstoken={TOKEN}&wsfunction={FUNCION}&moodlewsrestformat=json&{PARAMETROS}
```

## Servicio en Laravel

```php
// Registrado como singleton en MoodleServiceProvider
$moodle = app(\Modules\Moodle\Services\MoodleService::class);
```

Metodo generico de bajo nivel:
```php
$moodle->call(string $function, array $params): mixed
// Devuelve null en funciones que no retornan datos (ej: enrol, addUsersToGroup)
```

## Endpoints implementados

### core_user_create_users — Crear usuario
```php
$moodle->createUser([
    'username'  => string,  // email del alumno (patron del Panel)
    'password'  => string,  // ucfirst(nombre) + '4444*' (ej: Ana4444*)
    'firstname' => string,
    'lastname'  => string,
    'email'     => string,
]);
// Retorna: ['id' => int, 'username' => string]
```

### core_user_update_users — Actualizar usuario (ej: cambiar contrasena)
```php
$moodle->updateUserPassword(int $userId, string $password): void
```
- Se usa cuando el usuario ya existe en Moodle (reintento seguro)

### core_user_get_users — Buscar usuario por username
```php
$moodle->findUserByUsername(string $username): ?array
// Retorna array con datos del usuario o null si no existe
// Campos: id, username, firstname, lastname, email, ...
```
- Se usa antes de crear usuario para verificar si ya existe (flujo idempotente)

### enrol_manual_enrol_users — Matricular en curso
```php
$moodle->enrolInCourse(
    int $userId,
    int $courseId,
    int $roleId = 5,        // 5 = student
    ?int $timestart = null, // timestamp Unix de inicio (fecha_inicio del grupo)
    ?int $timeend = null,   // timestamp Unix de fin (fecha_fin del grupo, endOfDay)
    int $suspend = 0        // 0 = activo, 1 = suspendido
): array
```
- Matricula con fechas de inicio y fin configuradas en Moodle
- Se usa tambien para reactivar la matricula del tutor (suspend=0, timeend=fecha_fin del grupo)

### core_enrol_get_users_courses — Cursos donde esta matriculado un usuario
```php
$moodle->getUserCourses(int $userId): array
// Retorna array de cursos: [['id' => int, 'fullname' => string, ...], ...]
// Solo devuelve cursos activos (no suspendidos)
```
- Se usa para autodetectar el aula de Moodle del tutor al ejecutar matricula

### core_group_create_groups — Crear grupo en un curso
```php
$moodle->createGroup(int $courseId, string $name): int
// Retorna el ID del grupo creado
// name: formato '{accion}/{grupo}' (ej: '241/3')
```
- Requiere parametro `descriptionformat=1` (obligatorio en la API de Moodle)

### core_group_add_group_members — Agregar usuarios a un grupo
```php
$moodle->addUsersToGroup(int $groupId, array $userIds): void
// userIds: array de IDs de usuarios Moodle
```

### core_course_search_courses — Buscar cursos por texto
```php
$moodle->searchCourses(string $query, int $perpage = 10): array
// Retorna array de cursos: [['id' => int, 'fullname' => string, ...], ...]
```
- Usado en AccionesFormativasIndex para el autocomplete de vinculacion de cursos

### gradereport_user_get_grades_table — Obtener notas
```php
$moodle->getUserGrades(int $userId): array
```
- Util para seguimiento academico (Fase 5 del flujo, pendiente)

### testConnection — Verificar conexion
```php
$moodle->testConnection(): bool
```

## Flujo completo de matriculacion en Moodle

Implementado en `GrupoFormativo::ejecutarEnMoodle(int $moodleCourseId)`:

1. **Crear/reutilizar grupo Moodle**: nombre `{numero_accion}/{id_grupo_fundae}` (ej: `241/3`). Si ya existe un `moodle_group_id` en el grupo, se reutiliza
2. **Reactivar matricula del tutor**: llama `enrolInCourse` con `suspend=0` y `timeend=fecha_fin->endOfDay()` usando el `moodle_username` del tutor
3. **Por cada alumno** con `estado_moodle != 'matriculado'`:
   - Busca usuario por username (email del alumno)
   - Si no existe: crea usuario (`createUser`)
   - Si existe: actualiza contrasena (`updateUserPassword`)
   - Matricula en el curso con `timestart` y `timeend` del grupo
   - Agrega al grupo Moodle
   - Actualiza pivot: `moodle_user_id`, `moodle_username`, `estado_moodle='matriculado'`
   - Envia email con credenciales (`CredencialesMoodleMail`)
4. Si todos OK: grupo pasa a estado `en_curso`
5. Si hay errores y se alcanzan 2 intentos fallidos: notifica al admin por email

## Email de credenciales al alumno (`CredencialesMoodleMail`)

- **From**: `info@aula.1curso.com` (configurable via `MOODLE_MAIL_FROM`)
- **CC**: `tutorias@webcurso.es`
- **Contenido**: nombre del alumno, username, password, URL completa del curso (`{public_url}/course/view.php?id={courseId}`), fechas de inicio y fin, parrafo de bonificacion, contacto soporte (`administracion@webcurso.es`)

## Patron de credenciales

| Campo | Valor | Ejemplo |
|---|---|---|
| Username | Email del alumno | `ana.garcia@empresa.com` |
| Password | `ucfirst(nombre) + '4444*'` | `Ana4444*` |

- Si el usuario ya existe y se rematricula: se actualiza la contrasena al patron actual y se envia el email igualmente

## Plataforma Aulasystem (www.plataformateleformacion.com)

Cuando la accion formativa tiene `codigo_plataforma = 'a'`, NO se usa la API de Moodle.
- El Panel muestra el boton "Matriculado en Aulasystem" en lugar de "Matricular en Moodle"
- Al pulsar: todos los alumnos del grupo quedan con `estado_moodle = 'aulasystem'` y el grupo pasa a `en_curso`
- No se generan usuarios ni se envian emails automaticamente

## Notas tecnicas importantes

- `core_course_get_courses` falla con `errorcoursecontextnotvalid` en esta instancia de Moodle → usar `core_course_search_courses` en su lugar
- `core_group_create_groups` requiere `descriptionformat=1` o devuelve `invalidparameter`
- `call()` maneja respuestas `null` (algunas funciones devuelven null en exito, no un array)
- La conexion interna Docker necesita el header `Host` correcto para que Moodle no rechace la peticion
