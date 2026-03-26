# Ecosistema de herramientas

WebCurso utiliza multiples herramientas para distintos aspectos del negocio. El Panel no reemplaza ninguna, las complementa.

## Herramientas en uso

### Panel (esta aplicacion)
- **Rol:** Automatizacion de procesos administrativos internos
- **Tecnologia:** Laravel 12 + Livewire 3.6
- **Usuarios:** Solo personal administrativo de WebCurso
- **Alcance actual:** Gestion de candidatos, requisitos, recordatorios, importacion de datos FUNDAE, dashboard estadistico

### Zoho CRM
- **Rol:** Gestion de prospectos y comunicacion con clientes
- **Quien lo usa:** Equipo comercial y callcenter
- **Integracion con Panel:** Futura. Sincronizar candidatos para evitar doble entrada de datos
- **Estado integracion:** No iniciada

### Zoho Books
- **Rol:** Facturacion y contabilidad
- **Quien lo usa:** Administracion
- **Integracion con Panel:** Futura. Generar factura al completar matricula
- **Estado integracion:** No iniciada

### Zoho Campaigns
- **Rol:** Campanas de email marketing masivo
- **Quien lo usa:** Marketing
- **Integracion con Panel:** Sin integracion prevista

### Sendy
- **Rol:** Email marketing alternativo (mas economico para volumenes altos)
- **Quien lo usa:** Marketing
- **Integracion con Panel:** Sin integracion prevista

### Moodle
- **Rol:** Plataforma de aprendizaje virtual (LMS). Los alumnos realizan los cursos aqui
- **Quien lo usa:** Alumnos (formacion), administracion (gestion)
- **Integracion con Panel:**
  - Existe codigo inicial de API (crear usuario, matricular, obtener notas) pero NO esta funcional aun
  - Importacion de cursos/categorias desde CSV (comando artisan)
- **API:** REST con token. Endpoints: core_user_create_users, enrol_manual_enrol_users, gradereport_user_get_grades_table
- **Estado integracion:** Codigo inicial existe, pendiente de completar

### FUNDAE (portal web)
- **Rol:** Registro oficial de formacion bonificada. Fuente de datos de empresas, grupos y participantes
- **Interaccion actual:** Manual. Se descargan archivos XLS/CSV del portal y se importan al Panel
- **Portales:** Lanzadera (`empresas.fundae.es/Lanzadera`) y Tramites (`tramites.fundae.es`)
- **Acceso:** Certificado digital o Cl@ve. **No ofrece API REST publica**
- **Formatos de intercambio:** XLS/CSV (descarga), XML con XSD (cargas masivas de acciones, grupos, finalizacion)
- **Integracion con Panel:**
  - Actual: importacion de empresas (CSV), grupos (CSV), participantes bonificados (XLS)
  - Futura: automatizacion con Playwright para descarga y comunicaciones
  - Futura: generacion de XML conformes a XSD para cargas masivas
- **Estado integracion:** Solo importacion de datos, descarga manual
- **Ver:** `procedimientos-fundae.md` y `automatizacion-fundae.md` para detalle completo

### Mailpit (solo desarrollo)
- **Rol:** Captura de emails en entorno local
- **Acceso:** http://localhost:8025
- **Uso:** Verificar contenido y formato de emails antes de enviar en produccion

## Diagrama de relacion

```
Zoho CRM ──(futuro)──> PANEL <──(futuro)──> Zoho Books
                          │
                          ├── Moodle (API: usuarios, matriculas, notas)
                          │
                          └── FUNDAE (importacion XLS/CSV, futuro: Playwright)

Zoho Campaigns / Sendy ── (sin integracion) ── independientes
```

## Herramientas evaluadas y descartadas/aplazadas

| Herramienta | Evaluacion | Estado |
|---|---|---|
| **Zoho Creator** | Low-code insuficiente para la complejidad del Panel | Descartado |
| **Zapier** | Solo 2 triggers Moodle, caro para volumen | Descartado |
| **Make (Integromat)** | Limitaciones similares a Zapier | Descartado |
| **n8n** | Self-hosted, gratuito, Docker. Buena opcion futura para orquestacion | Aplazado |

## Software del sector (competencia)

No existe ecosistema open-source para gestion FUNDAE. Alternativas comerciales evaluadas:
- **Gesforma** — SaaS gestion FUNDAE, precios opacos, modelo consultivo
- **iFormalia** — Gestor formacion bonificada, Madrid
- **Conpas S-ERP** — ERP formacion, genera XML FUNDAE
- Ninguno ofrece integracion con ecosistema Zoho+Moodle

**Ver:** `estrategia-sistemas.md` para analisis detallado de distribucion Panel vs Zoho

## Clave de referencia cruzada

El **CIF** (Numero de Identificacion Fiscal) es el identificador comun entre todos los sistemas:
- Panel: campo `cif` en tablas empresas, grupos, participantes_bonificados
- FUNDAE: identificador principal de empresa
- Zoho CRM: campo personalizado
- Zoho Books: NIF/CIF del cliente
