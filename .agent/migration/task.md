# Lista de Tareas: Intranet Modular MasDesarrollo (MIGRADO)

- [x] **Fase 1: Entorno y Core**
  - [x] Configurar `composer.json` para módulos personalizados (`Modules/`) <!-- id: 0 -->
  - [x] Crear estructura de directorios `Modules/` <!-- id: 1 -->
  - [x] Instalar Laravel Breeze con stack Livewire <!-- id: 2 -->
  - [x] Deshabilitar registro público en Breeze <!-- id: 3 -->
  - [x] Verificar entorno Sail/WSL2 <!-- id: 4 -->

- [x] **Fase 2: Módulo de Integración Moodle**
  - [x] Crear `Modules/Moodle/Services/MoodleService.php` <!-- id: 5 -->
  - [x] Crear `Modules/Moodle/Providers/MoodleServiceProvider.php` <!-- id: 6 -->
  - [x] Registrar `MoodleServiceProvider` en `bootstrap/providers.php` <!-- id: 7 -->
  - [x] Configurar variables `.env` para Moodle (`MOODLE_URL`, `MOODLE_TOKEN`) <!-- id: 8 -->
  - [x] Implementar `createUser`, `enrolInCourse`, `getUserGrades` en `MoodleService` <!-- id: 9 -->

- [x] **Fase 3: UI/UX Administrativa (Livewire)**
  - [x] Crear diseño de Dashboard con barra lateral colapsable <!-- id: 10 -->
  - [x] Crear Componentes Blade para elementos de UI <!-- id: 11 -->
  - [x] Implementar Componente Livewire de Gestión de Usuarios (`UserManagement`) <!-- id: 12 -->
  - [x] Conectar eventos de `UserManagement` a `MoodleService` <!-- id: 13 -->

- [x] **Fase 4: Seguridad, Roles y Middleware**
  - [x] Instalar Spatie Laravel Permission <!-- id: 14 -->
  - [x] Definir Roles: SuperAdmin, Gestor, Usuario <!-- id: 15 -->
  - [x] Aplicar Middleware y restricciones de Roles (Seeder SuperAdmin creado) <!-- id: 16 -->

- [ ] **Revisión Final y Despliegue**
  - [ ] Verificar todos los requisitos contra `docs/masdesarrollo_project.md` <!-- id: 17 -->
  - [ ] Pruebas de integración reales con Moodle <!-- id: 18 -->
