# Plan de Implementación: Intranet Modular masdesarrollo.es

Este plan sigue las especificaciones de `docs/masdesarrollo_project.md`.

## Objetivo
Crear una Intranet Modular con Laravel 12 + Livewire 3 + Moodle + Sail/WSL2, usando una arquitectura modular manual (PSR-4).

## Fases del Proyecto

### Fase 1: Entorno y Core
- [x] Instalar Laravel 12 vía Sail.
- [x] Configurar autoload PSR-4 para `Modules\`.
- [x] Instalar Laravel Breeze con stack Livewire.
- [x] Eliminar registro público (solo Admins crean cuentas).

### Fase 2: Módulo de Integración Moodle
- [x] Crear estructura del módulo (`Modules/Moodle`).
- [x] Configurar `MoodleServiceProvider`.
- [x] Crear `MoodleService` para comunicación con API.
- [x] Validar funciones básicas (`createUser`, `enrolInCourse`, `getUserGrades`).
- [ ] Configurar variables de entorno `.env` (`MOODLE_URL`, `MOODLE_TOKEN`).

### Fase 3: UI/UX Administrativa (Livewire)
- [ ] Dashboard principal con sidebar colapsable.
- [ ] Componentes Blade reutilizables.
- [ ] Gestión de Usuarios (CRUD Livewire).

### Fase 4: Seguridad, Roles y Middleware
- [x] Instalar Spatie Laravel Permission.
- [x] Crear migraciones de Roles y Permisos.
- [x] Crear migración para campo `username` en tabla `users`.
- [ ] Definir Roles: SuperAdmin, Gestor, Usuario.
- [ ] Aplicar Middleware.
