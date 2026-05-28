# CLAUDE.md

**WebCurso** es una empresa espanola de formacion online bonificada por FUNDAE. Este proyecto ("Panel") automatiza los procesos administrativos internos: gestion de candidatos, requisitos, recordatorios, importacion de datos FUNDAE e integracion con Moodle. Ver `.claude/rules/` para contexto completo del negocio y dominio.

## Documentacion de dominio (.claude/rules/)

| Archivo | Contenido |
|---|---|
| `negocio.md` | Modelo de negocio, tipos de cliente, objetivo del Panel |
| `terminologia.md` | Glosario: candidato, alumno, matricula, bonificacion, FUNDAE... |
| `flujo-completo.md` | Flujo de negocio completo (7 fases), que esta desarrollado y que falta |
| `ecosistema-herramientas.md` | Zoho CRM/Books/Campaigns, Moodle, Sendy, FUNDAE y como encaja el Panel |
| `estado-actual-panel.md` | Modulos completados, inventario tecnico, pendientes, decisiones de diseno |
| `arquitectura-tecnica.md` | Modelos, Livewire, servicios, cron, Docker (8 servicios), configs |
| `api-moodle.md` | Referencia API Moodle (endpoints, auth, estado de integracion) |
| `api-zoho.md` | Placeholder: futura integracion Zoho CRM |
| `estilos-ui.md` | Placeholder: convenciones de UI |
| `procedimientos-fundae.md` | Flujo administrativo FUNDAE (8 fases), datos requeridos, normativa, XML/XSD |
| `estrategia-sistemas.md` | Distribucion Panel vs Zoho vs herramientas: que va donde y por que |
| `automatizacion-fundae.md` | Automatizacion portal FUNDAE (Playwright) y generacion de XML para cargas masivas |
| `testing-y-simulacion.md` | Estrategia de testing: Pest, Dusk, Playwright, mocks, factories, que testear por modulo |

## Entorno de desarrollo

Laravel Sail (Docker). Todos los comandos PHP/Composer/Artisan deben ejecutarse via Sail:

```bash
./vendor/bin/sail up -d          # Levantar servicios
./vendor/bin/sail down           # Detener servicios
./vendor/bin/sail shell          # Terminal en contenedor PHP
```

## Comandos principales

```bash
# Setup inicial completo
composer setup   # instala deps, genera key, ejecuta migrate, npm install

# Desarrollo
composer dev     # levanta concurrently: server, queue, logs, vite

# Frontend
npm run dev      # Vite dev server con HMR
npm run build    # Build de produccion

# Tests (SQLite in-memory)
composer test                         # limpia config + ejecuta Pest
./vendor/bin/sail php artisan test    # alternativa

# Base de datos
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed
```

### Comandos Artisan propios

```bash
php artisan candidatos:enviar-recordatorios           # Enviar recordatorios pendientes
php artisan candidatos:enviar-recordatorios --dry-run  # Preview sin enviar
php artisan candidatos:enviar-resumen                  # Resumen diario al admin
php artisan moodle:importar-cursos                     # Importar cursos desde CSV
php artisan webcurso:import-legacy                     # Importar datos del sistema legacy
php artisan alumnos:importar-bonificados               # Crear alumnos desde participantes_bonificados + pool local (auto-trigger tras XLS)
php artisan alumnos:importar-bonificados --dry-run     # Preview sin insertar
php artisan alumnos:migrar-legacy                       # One-shot: snapshot tbl_member → alumnos_legacy_pool + alumnos directos + cursos
php artisan alumnos:migrar-legacy --solo-pool          # Solo poblar alumnos_legacy_pool
php artisan alumnos:migrar-legacy --solo-alumnos       # Solo crear alumnos a partir del pool
php artisan alumnos:migrar-legacy --solo-cursos        # Solo poblar alumnos_legacy_cursos (historial)
php artisan alumnos:enriquecer-cursos-legacy           # Rellena acción/grupo de cursos legacy via tabla `grupos` y `participantes_bonificados`
php artisan alumnos:auditar-enriquecimiento-legacy     # Read-only: clasifica cursos legacy sin acción en 6 escalones. Flags: --year=YYYY --csv=path
php artisan bonificados:enviar-email-saldo             # Cron mensual de email de saldo a participantes
php artisan bonificados:enviar-email-saldo --dry-run   # Preview sin enviar
```

## Testing

Pest framework. SQLite in-memory, sync queue, array cache/session (configurado en `phpunit.xml`).

```bash
./vendor/bin/sail php artisan test --filter NombreDelTest   # Test individual
./vendor/bin/sail php artisan test tests/Feature/Auth/      # Directorio
```
