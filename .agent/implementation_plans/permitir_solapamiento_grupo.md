# Excepción de solapamiento de fechas por grupo formativo

## Contexto
Hay que matricular a 8 alumnos de Drivania en "Claude Code Avanzado Agentes" (24/10–31/12/2026), pero ya están en otro grupo con fechas solapadas. `Alumno::tieneGrupoActivoEnPeriodo()` lo bloquea en todas las rutas de alta. La usuaria quiere una **casilla por grupo** "Permitir solapamiento de fechas", versionada en git, para que en pruebas baste con desplegar y migrar.

⚠️ **Urgente:** el grupo aún no existe. Si es un grupo bonificado propio, con inicio 24/10 `estaAbierto()` solo deja añadir alumnos **hoy 22/10** (regla de 2 días). Mañana ya no se podrían añadir.

## Cambios

1. **Migración** `database/migrations/2026_10_22_000001_add_permite_solapamiento_to_grupos_formativos_table.php`
   - Añade `permite_solapamiento` boolean, default false, después de `gestion_externa`.

2. **`app/Models/GrupoFormativo.php`**
   - Añadir el campo a `$fillable` y a `casts` (boolean).

3. **`app/Models/Alumno.php` → `tieneGrupoActivoEnPeriodo()`**: es el único punto de control. Así cubre las 5 llamadas de `MatriculacionPanel` y las 2 de la vista, sin tocarlas.
   - Si el grupo destino (`$excluirGrupoId`) tiene `permite_solapamiento = true`, devolver `false`.
   - Excluir de la consulta los grupos marcados con `permite_solapamiento`, para que un grupo excepcional tampoco bloquee altas futuras en otros grupos.
   - Actualizar el docblock con la excepción.
   - El límite de 80 alumnos por tutor y tramo sigue aplicándose igual.

4. **`app/Livewire/Webcurso/MatriculacionPanel.php`**
   - Propiedad `nuevoPermiteSolapamiento` en el alta: se guarda en `crearGrupo()` (línea ~888) y se resetea en `resetFormGrupo()`.
   - Propiedad equivalente en la edición: se carga al abrir y se guarda en `actualizarGrupo()` (línea ~1560).

5. **`resources/views/livewire/webcurso/matriculacion-panel.blade.php`**
   - Casilla "Permitir solapamiento de fechas con otros grupos del alumno (excepción)" en los formularios de alta y edición, junto a la de gestión externa.
   - Badge ámbar `Solapamiento permitido` en la cabecera del grupo, junto al badge `Externo` (línea ~173).

6. **Test** `tests/Feature/Webcurso/GrupoSolapamientoTest.php`, siguiendo el patrón de `GrupoExternoTest.php`. Casos:
   - Sin la casilla, el alumno con un grupo solapado sigue bloqueado.
   - Con la casilla en el grupo destino, el alumno entra.
   - Un grupo con la casilla no bloquea altas en otro grupo normal.

## Orden de despliegue: primero pruebas, luego producción

### Fase A — Pruebas
1. Crear la rama `feature/permitir-solapamiento` y aplicar los cambios 1–6.
2. Ejecutar `./vendor/bin/sail artisan migrate`. Solo añade una columna con valor por defecto false, así que no afecta a los grupos existentes.
3. Ejecutar los tests (ver Verificación).
4. Prueba manual en pruebas con un alumno que ya tenga un grupo en curso:
   - Crear un grupo solapado **sin** la casilla: debe salir el error de solapamiento.
   - Crear otro **con** la casilla: el alumno entra y se ve el badge.
   - Comprobar que el límite de 80 alumnos por tutor y tramo sigue funcionando.
5. Commit y push de la rama. Merge a `main` cuando esté validado.

### Fase B — Producción
1. Antes de migrar, hacer una copia de seguridad de la BD (método dump habitual).
2. Ejecutar `git pull` de `main` y `php artisan migrate`.
3. Limpiar cachés: `php artisan optimize:clear`, y `npm run build` si cambia la vista compilada.
4. En el candidato de Drivania, crear el grupo "Claude Code Avanzado Agentes" del 24/10 al 31/12 **con la casilla marcada**, y añadir los alumnos.
   - Faltan datos de algunos: Osvald, Igor y Fransheska no tienen NIF ni email, y a Gerard Martret le falta el NIF.
   - Ojo: Osvald e Igor comparten el apellido "Martret". Hay que confirmar que son personas distintas.
5. Matricular en Moodle como siempre.

⚠️ **Plazo:** si es un grupo bonificado propio con inicio el 24/10, la regla de 2 días solo permite añadir alumnos hasta **hoy 22/10**. Si las fases A y B no terminan hoy, hay dos opciones:
- Retrasar la fecha de inicio.
- Que el grupo sea externo, que no tiene esa regla.

## Verificación
- `./vendor/bin/sail php artisan test --filter GrupoSolapamientoTest`, más `GrupoExternoTest` y `MatriculaParticularTest` como regresión.
- Manual en pruebas:
  - Crear un grupo solapado sin la casilla: aparece el error "ya está en otro grupo con fechas que se solapan".
  - Con la casilla: el alumno se añade y se ve el badge.
