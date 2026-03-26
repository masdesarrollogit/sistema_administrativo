# Instrucciones para agentes de IA en este workspace

## Resumen del proyecto
Este proyecto es una aplicación web basada en Laravel, utilizando Laravel Sail para el entorno de desarrollo y servicios Dockerizados. Incluye integración con Vite, TailwindCSS y PHPUnit para pruebas.

## Comandos principales
- **Desarrollo local:**
  - Levantar entorno: `./vendor/bin/sail up -d`
  - Parar entorno: `./vendor/bin/sail down`
  - Acceso a contenedor: `./vendor/bin/sail shell`
- **Instalación de dependencias:**
  - PHP: `./vendor/bin/sail composer install`
  - Node: `npm install`
- **Compilación frontend:**
  - Desarrollo: `npm run dev`
  - Producción: `npm run build`
- **Pruebas:**
  - Ejecutar tests: `./vendor/bin/sail php artisan test` o `./vendor/bin/sail phpunit`

## Convenciones y estructura
- Código fuente principal en `app/`
- Rutas en `routes/`
- Vistas en `resources/views/`
- Pruebas en `tests/`
- Configuración Docker en `compose.yaml`
- Configuración de PHPUnit en `phpunit.xml`

## Recomendaciones para agentes
- Usar comandos Sail para cualquier tarea PHP, Composer o Artisan.
- Preferir scripts npm para tareas frontend.
- Validar cambios ejecutando pruebas automáticas.
- Seguir la estructura de carpetas y convenciones de Laravel.

## Ejemplos de prompts útiles
- "Agrega un nuevo comando Artisan personalizado."
- "Crea un test de integración para el controlador X."
- "Agrega una migración para la tabla Y."
- "Actualiza la configuración de Vite para Tailwind."

## Personalizaciones sugeridas
- Instrucciones específicas para Livewire, Actions o Forms si el proyecto crece.
- Hooks para validación automática tras cada cambio en `app/` o `routes/`.
- Agentes especializados para frontend (Vite/Tailwind) y backend (Laravel/Artisan).
