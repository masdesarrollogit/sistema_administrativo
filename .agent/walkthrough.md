# Guía de Inicio y Verificación - Intranet Modular

Esta guía describe cómo levantar el proyecto en el nuevo entorno WSL2 y verificar los módulos implementados.

## 1. Prerrequisitos
- **WSL2** (Ubuntu) instalado y activo.
- **Docker Desktop** corriendo en Windows con integración WSL2 habilitada.
- **PHP 8.3** y **Composer** instalados en el entorno WSL.

## 2. Instalación y Configuración Inicial

Si acabas de clonar o mover el proyecto:

1. **Instalar dependencias de PHP:**
   ```bash
   composer install
   ```

2. **Copiar configuración de entorno:**
   ```bash
   cp .env.example .env
   ```

3. **Configurar Variables Moodle:**
   Edita el archivo `.env` y añade tus credenciales (si no están):
   ```ini
   MOODLE_URL=http://tu-moodle-url.com
   MOODLE_TOKEN=tu_token_generado
   ```

4. **Levantar Entorno (Sail):**
   ```bash
   ./vendor/bin/sail up -d
   ```

5. **Generar Key:**
   ```bash
   ./vendor/bin/sail artisan key:generate
   ```

## 3. Base de Datos y Migraciones

El proyecto incluye migraciones para **Roles (Spatie)** y **Username**.

1. **Ejecutar migraciones:**
   ```bash
   ./vendor/bin/sail artisan migrate
   ```

2. **Validar Tablas:**
   Entrar a la base de datos o verificar que existan:
   - `users` (con columna `username`)
   - `roles`, `permissions`, `model_has_roles`, etc.

## 4. Módulo de Integración Moodle

El servicio se encuentra en `Modules/Moodle/Services/MoodleService.php`.

### Prueba Manual (Tinker)
Para verificar la conexión sin interfaz gráfica:

```bash
./vendor/bin/sail artisan tinker
```

Dentro de Tinker:
```php
$moodle = new \Modules\Moodle\Services\MoodleService();
// Probar obtener notas (reemplaza 123 con un ID real de Moodle)
$moodle->getUserGrades(123);
```

## 5. Desarrollo Frontend (Livewire)

1. **Instalar dependencias JS:**
   ```bash
   npm install
   ```

2. **Compilar assets (Hot Replacement):**
   ```bash
   npm run dev
   ```

## 6. Siguientes Pasos
- Implementar el CRUD de usuarios en `app/Livewire/Users`.
- Configurar el Dashboard administrativo.
