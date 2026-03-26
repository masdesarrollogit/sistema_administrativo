# Testing y simulacion

Estrategia de pruebas del Panel. Las herramientas se aplican segun el modulo y la necesidad.

## Herramientas disponibles

### Pest (tests unitarios y feature)
- **Ya configurado.** Framework principal de testing PHP
- Base de datos: SQLite in-memory (phpunit.xml)
- Queue: sync, Cache/Session: array
- Comandos: `composer test` o `./vendor/bin/sail php artisan test`
- **Usar para:** validar logica de negocio, reglas de dominio, scopes, servicios

### Mailpit (testing de emails)
- **Ya configurado.** Captura todos los emails en desarrollo
- Acceso: http://localhost:8025
- SMTP: localhost:1025
- **Usar para:** verificar contenido, formato y adjuntos de recordatorios, notificaciones de matricula, emails de saldo

### Selenium + Laravel Dusk (tests de navegador)
- **Contenedor Docker existe** (selenium/standalone-chromium), pero no hay tests Dusk escritos aun
- **Usar para:** tests E2E que simulan un usuario real interactuando con el Panel (formularios Livewire, flujos completos)

### Playwright (automatizacion y E2E)
- **No instalado aun.** Previsto para automatizacion del portal FUNDAE (ver automatizacion-fundae.md)
- Tambien sirve como alternativa a Dusk para tests E2E mas potentes
- **Usar para:** automatizacion FUNDAE, tests E2E complejos, testing cross-browser

### Mockery (mock de APIs externas)
- **Disponible via Pest/PHPUnit.** No requiere instalacion adicional
- **Usar para:** simular respuestas de Moodle API, Zoho API, FUNDAE sin depender de los servicios reales
- Patron: mockear MoodleService como singleton en el contenedor de Laravel durante tests

### Factories y Seeders (datos de prueba)
- **Pendiente de crear.** Necesarios antes de poder testear cualquier modulo
- **Usar para:** poblar el Panel con datos realistas de candidatos, empresas, requisitos, grupos, participantes

## Prioridad de implementacion

La prioridad depende del modulo que se este desarrollando. Orden logico general:

1. **Factories + Seeders** — base para todo lo demas. Sin datos realistas no se puede probar nada
2. **Tests Pest** — al desarrollar cada modulo, escribir tests de logica de negocio
3. **Mock de APIs** — al integrar Moodle y Zoho, mockear servicios externos
4. **Tests E2E (Dusk o Playwright)** — cuando los flujos esten completos, testear de punta a punta

## Que testear por modulo

### Candidatos y requisitos (ya desarrollado, tests pendientes)
- Inicializacion de requisitos segun tipo de candidato
- Transicion de estados: pendiente → en_proceso → completado
- Auto-completar candidato cuando todos los requisitos obligatorios estan completos
- Pausado automatico al alcanzar maximo de recordatorios
- Scope listosParaRecordatorio (frecuencia, dia de la semana)

### Importacion de datos (ya desarrollado, tests pendientes)
- UPSERT de empresas por CIF
- Truncate + insert de grupos y participantes
- Conversion de formatos EU (numeros, fechas, porcentajes)
- Manejo de archivos vacios, mal formateados, con BOM

### Matricula (pendiente de desarrollar)
- Un candidato no puede matricularse si tiene requisitos pendientes
- No puede tener cursos simultaneos (secuencial)
- Generacion de credenciales Moodle segun patron configurable
- Creacion de usuario en Moodle (mock de MoodleService)
- Envio de email con credenciales al alumno

### Recordatorios (ya desarrollado, tests pendientes)
- Envio solo a candidatos listos (scope, frecuencia, limite)
- Campo observacion NO se incluye en el email
- Descripcion personalizada SI se incluye
- Adjuntos correctos (archivos del candidato + del tipo de requisito)
- Logging en notificaciones_log (exito y error)
