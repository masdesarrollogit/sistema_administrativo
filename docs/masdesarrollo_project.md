# 🚀 Prompt para Google Antigravity  
## Proyecto: Intranet Modular masdesarrollo.es (Laravel 12 + Livewire 3 + Moodle + Sail/WSL2)

Actúa como un **Arquitecto de Software Senior y Desarrollador Full-Stack experto en Laravel**.  
Tu misión es **generar el código base y la estructura del proyecto** siguiendo exactamente este plan (sin inventar tecnologías ni añadir librerías de modularización).

---

## 1) 📦 Stack Tecnológico (Obligatorio)

Implementa la intranet con:

- **Core:** Laravel 12 (PHP 8.3+)
- **Frontend:** Livewire 3 + Alpine.js + Tailwind CSS
- **Autenticación:** Laravel Breeze (sin registro público)
- **Entorno:** Laravel Sail (Docker Desktop en Windows 11 + WSL2)
- **Permisos:** Spatie Laravel Permission

✅ Prioridad: **Livewire 3 para toda la interactividad**.

---

## 2) 🧩 Arquitectura Modular Manual (PSR-4, sin librerías externas)

Debes crear una modularización propia con estructura:

```
/root
  ├── app/
  ├── Modules/
  │   └── Moodle/
  │       ├── Http/
  │       │   └── Livewire/       # Componentes de UI de Moodle
  │       ├── Services/           # Lógica de comunicación API
  │       ├── Providers/          # Registro del módulo
  │       └── Routes/             # Rutas exclusivas del módulo
  ├── composer.json
  └── docker-compose.yml
```

Configura `composer.json` para reconocer `Modules\`:

```json
"autoload": {
  "psr-4": {
    "App\\": "app/",
    "Modules\\": "Modules/",
    "Database\\Factories\\": "database/factories/",
    "Database\\Seeders\\": "database/seeders/"
  }
}
```

Luego ejecuta:

```
composer dump-autoload
```

---

## 3) 🧱 Fase 1: Entorno y Core

Objetivo: **Levantar el contenedor y habilitar la carga modular**.

Debes:
1. Instalar Laravel 12 vía Sail.
2. Asegurar que el autoload PSR-4 de `Modules\` funcione.
3. Instalar Laravel Breeze con stack **Livewire**.
4. **Eliminar registro público:** quitar la ruta de registro en `routes/auth.php` para que **solo Admins creen cuentas**.

Entrega esperada en esta fase:
- Proyecto ejecutándose en Sail/WSL2
- Breeze funcionando solo con login
- Estructura `Modules/` reconocida por Composer

---

## 4) 🔗 Fase 2: Módulo de Integración Moodle

Objetivo: **Crear el puente entre la Intranet y Moodle vía REST API**.

Crea:

📁 `Modules/Moodle/Services/MoodleService.php`  
Responsabilidad: centralizar llamadas a Moodle usando `Http` de Laravel.

Funciones mínimas:
- `createUser()`
- `enrolInCourse()`
- `getUserGrades()`

Configura `.env` con:
- `MOODLE_URL`
- `MOODLE_TOKEN`

Además:
- Crea un `MoodleServiceProvider` dentro de `Modules/Moodle/Providers/` para registrar el módulo (rutas, servicios, etc).

---

## 5) 🎨 Fase 3: UI/UX Administrativa (Livewire)

Objetivo: interfaz moderna y reactiva.

Implementa:
- **Dashboard principal** con sidebar colapsable
- Uso de **Blade Components** para botones/inputs/alertas

### Gestión de usuarios (Componente Livewire)
- Formulario para crear usuario
- Al guardar en Laravel, dispara un evento que llama a `MoodleService`
- Si falla Moodle:
  - registrar en logs
  - notificar al Admin
  - **NO borrar** el usuario local

---

## 6) 🛡️ Fase 4: Seguridad, Roles y Middleware

Objetivo: restringir accesos con roles.

Roles:
- **SuperAdmin:** acceso total
- **Gestor:** solo gestión de alumnos en Moodle
- **Usuario:** acceso a reportes + enlace a Moodle

Middleware:
- aplicar `auth`
- aplicar `role:SuperAdmin` en rutas de configuración

---

## 7) ✅ Checklist de Despliegue (Rápido)

Debes seguir este orden y marcarlo con evidencias (archivos creados, comandos, rutas, etc):

- [ ] Instalar Laravel + Sail en WSL2
- [ ] Modificar `composer.json` y crear `Modules/`
- [ ] Instalar Breeze y limpiar rutas de registro
- [ ] Crear `MoodleServiceProvider`
- [ ] Desarrollar `MoodleService`
- [ ] Crear componentes Livewire para CRUD de usuarios
- [ ] Probar sincronización Docker ↔ Moodle

---

## 8) 📌 Reglas de entrega (Importante)

Quiero que respondas con:

1. **Plan de implementación por fases** (breve y ordenado)
2. **Estructura de carpetas final**
3. **Archivos clave con código completo** (mínimo: Service Provider, MoodleService, rutas del módulo, componente Livewire inicial)
4. **Comandos necesarios** (Sail, Breeze, autoload, migraciones, permisos)
5. **Notas de seguridad** (registro deshabilitado, middleware, logs de fallo Moodle)

No añadas nada fuera del stack. No uses librerías externas para modularización. Prioriza Livewire 3.

---

## 🎯 Instrucción de arranque

Comienza por: **Fase 1 (Sail + Laravel 12 + autoload Modules + Breeze sin registro)**  
y luego continúa en orden hasta completar el checklist.
