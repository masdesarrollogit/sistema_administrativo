# Plan de Implementación - Intranet Modular MasDesarrollo (MIGRADO)

## Descripción del Objetivo
Implementar una Intranet modular usando Laravel 12+, Livewire 3 y una arquitectura propia de `Modules` (PSR-4) sin librerías externas. El sistema integrará Moodle, gestionará usuarios y tendrá control de acceso basado en roles.

## Revisión de Usuario Requerida
> [!IMPORTANT]
> Confirmar si `Laravel 12` es explícitamente requerido o si la última versión estable (actualmente Laravel 11 o futura 12) es aceptable.
> Confirmar si las credenciales de `Moodle` (`MOODLE_URL`, `MOODLE_TOKEN`) están disponibles para pruebas.

## Cambios Propuestos

### Fase 1: Configuración de Entorno y Core
#### [MODIFICAR] [composer.json](file:///home/greicy/proyectos/mi-proyecto/composer.json)
- Agregar `"Modules\\": "Modules/"` al autoload `psr-4`.
- Ejecutar `composer dump-autoload`.

#### [NUEVO] [Directorio de Módulos](file:///home/greicy/proyectos/mi-proyecto/Modules)
- Crear estructura de directorios para `Modules/Moodle`.

#### [EJECUTAR] Instalación de Laravel Breeze
- Instalar Breeze con stack Livewire.
- `php artisan breeze:install livewire`

#### [MODIFICAR] [routes/auth.php](file:///home/greicy/proyectos/mi-proyecto/routes/auth.php)
- Eliminar/Comentar rutas de registro (`register`) para deshabilitar inscripciones públicas.

### Fase 2: Módulo de Integración Moodle
#### [NUEVO] [Modules/Moodle/Services/MoodleService.php](file:///home/greicy/proyectos/mi-proyecto/Modules/Moodle/Services/MoodleService.php)
- Implementar `createUser`, `enrolInCourse`, `getUserGrades` usando cliente `Http` de Laravel.

#### [NUEVO] [Modules/Moodle/Providers/MoodleServiceProvider.php](file:///home/greicy/proyectos/mi-proyecto/Modules/Moodle/Providers/MoodleServiceProvider.php)
- Arrancar/Registrar los servicios y rutas del módulo.

#### [MODIFICAR] [bootstrap/providers.php](file:///home/greicy/proyectos/mi-proyecto/bootstrap/providers.php)
- Registrar `MoodleServiceProvider`.

### Fase 3: UI/UX y Livewire
#### [NUEVO] [Componente UserManagement](file:///home/greicy/proyectos/mi-proyecto/Modules/Moodle/Http/Livewire/UserManagement.php)
- Crear componente Livewire para gestión de usuarios.
- Manejar evento `UserCreated` para disparar `MoodleService`.

#### [MODIFICAR] [resources/views/dashboard.blade.php](file:///home/greicy/proyectos/mi-proyecto/resources/views/dashboard.blade.php)
- Implementar barra lateral y nueva estructura de diseño.

### Fase 4: Seguridad
#### [EJECUTAR] Spatie Permission
- Instalar `spatie/laravel-permission`.
- Ejecutar migraciones.
- Crear Seeder para Roles (SuperAdmin, Gestor, Usuario).

## Plan de Verificación

### Pruebas Automatizadas
- `php artisan test` (Verificar pruebas básicas de Laravel)
- `php artisan tinker` (Probar conexión `MoodleService` manualmente)

### Verificación Manual
1. **Autoload de Módulos**: Verificar si las clases en `Modules/` cargan correctamente.
2. **Registro**: Verificar que `/register` devuelva 404 o redirija.
3. **Sincronización Moodle**: Crear un usuario y verificar el log/mock de llamada API.
4. **Roles**: Ingresar como 'Usuario' e intentar acceder a rutas de Admin.
