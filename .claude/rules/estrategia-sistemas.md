# Estrategia de sistemas — Panel vs Zoho vs herramientas externas

Analisis realizado en marzo 2026. Define que funcionalidad va en cada sistema y por que.

## Principio rector

Cada sistema hace lo que mejor sabe hacer:
- **Zoho CRM** → gestion comercial y captacion (Fase 1)
- **Panel (Laravel)** → operaciones FUNDAE, requisitos, Moodle (Fases 2-5)
- **Zoho Books** → facturacion (Fase 6)
- **CIF** como clave de referencia cruzada entre todos los sistemas

## Hallazgo clave: no hay alternativa open-source

No existe ecosistema open-source para gestion de formacion bonificada FUNDAE. Busqueda en GitHub: solo guias informativas, no herramientas de gestion. El mercado esta dominado por SaaS comerciales propietarios (Gesforma, iFormalia) con precios opacos y modelo consultivo.

**Conclusion:** El enfoque de construir un Panel custom con Laravel es competitivo y ofrece control total.

## Distribucion de modulos por sistema

| # | Modulo | Sistema | Justificacion |
|---|---|---|---|
| 1 | Captacion de prospectos | Zoho CRM | Ya funciona ahi, es su proposito |
| 2 | Gestion de candidatos | **Hibrido** | Zoho CRM origina → webhook → Panel gestiona |
| 3 | Requisitos y estados | Panel | Logica muy especifica, nucleo del valor del Panel |
| 4 | Recordatorios automaticos | Panel | Adjuntos dinamicos, frecuencia personalizada, pausado automatico |
| 5 | Importacion FUNDAE | Panel | CSV/XLS con transformaciones especificas, no es funcion de CRM |
| 6 | Dashboard estadistico | Panel | Datos de FUNDAE que no estan en Zoho |
| 7 | Gestion de empresas | **Hibrido** | Datos basicos en Zoho CRM (comercial), datos FUNDAE en Panel |
| 8 | Participantes bonificados | Panel | Datos 100% operativos de FUNDAE |
| 9 | Matriculacion | Panel | Requiere integracion Moodle API directa |
| 10 | Seguimiento academico | Panel | Datos de Moodle (notas, progreso) |
| 11 | Facturacion | **Zoho Books** | Herramienta diseñada para esto |
| 12 | Integracion Moodle | Panel | Laravel + PHP superior a Deluge para API |

## Arquitectura de integracion

```
ZOHO CRM                          PANEL (Laravel)                    MOODLE
-----------                       ---------------                    ------
Fase 1: Captacion                 Fase 2-3: Requisitos               Fase 4-5: Formacion
- Prospectos/Leads                - Candidatos (sync desde CRM)
- Contactos                       - Requisitos y documentos
- Empresas (datos comerciales)    - Recordatorios automaticos
                                  - Importacion FUNDAE
          |                       - Empresas (datos FUNDAE)
          |--- sync CIF/datos --> - Dashboard
          |    basicos            - Participantes bonificados
          |                       - Matriculacion ---------------------> API Moodle
          |<-- sync estado ---    - Seguimiento academico <------------- API Moodle
          |    matricula

ZOHO BOOKS
-----------
Fase 6: Facturacion
- Panel notifica via API/webhook cuando se completa matricula
- Books genera factura automaticamente
```

## Integraciones a desarrollar

### Zoho CRM → Panel (prioridad alta)
- **Trigger:** Por definir. El saldo disponible se verifica en FUNDAE, se carga en el Panel, y se envia al cliente por email desde el Panel ("Enviar saldo"). El Panel participa en el flujo comercial ANTES de crear el candidato. El trigger de Zoho CRM debe ser posterior a la verificacion de saldo y al acuerdo con el cliente
- **Flujo real:** Zoho CRM (prospecto) → Panel (verificar saldo, enviar email saldo) → Negociacion → Acuerdo → Crear candidato en Panel
- **Mecanismo:** Webhook de Zoho CRM → endpoint API en Laravel
- **Datos:** Nombre, email, telefono, empresa (CIF), tipo de candidato, curso(s) acordados
- **Resultado:** Candidato creado automaticamente en el Panel con requisitos inicializados
- **Beneficio:** Elimina la doble entrada de datos (mayor dolor actual)

### Panel → Zoho CRM (prioridad media)
- **Trigger:** Cambios de estado significativos (requisitos completos, matriculado, finalizado)
- **Mecanismo:** HTTP request desde Laravel a Zoho CRM API
- **Datos:** Estado del candidato, fecha de matriculacion, estado academico
- **Beneficio:** Equipo comercial ve progreso sin entrar al Panel

### Panel → Zoho Books (prioridad baja, fase futura)
- **Trigger:** Matriculacion completada
- **Mecanismo:** API de Zoho Books desde Laravel
- **Datos:** Cliente (CIF, razon social), curso, importe, datos fiscales
- **Beneficio:** Factura creada automaticamente
- **Estrategia de implementacion:** Iniciar con mocks (MockFacturacionService) ya que actualmente las facturas se generan en otro sistema. Migrar gradualmente a ZohoBooksFacturacionService cuando se decida la transicion. Patron: interfaz FacturacionService con dos implementaciones

## Herramientas de automatizacion evaluadas

| Herramienta | Veredicto | Notas |
|---|---|---|
| **Zapier** | Limitado | Solo 2 triggers Moodle, templates premium, caro |
| **Make (Integromat)** | Limitado | Similar a Zapier, limitaciones en Moodle |
| **n8n** | Prometedor | Self-hosted, gratuito, Docker. Considerar si los flujos se complican |
| **Zoho Flow** | Parcial | Util para Zoho-Zoho, limitado para Moodle |
| **Zoho Creator** | Descartado | Low-code insuficiente para la complejidad del Panel |

### n8n como opcion futura
- Self-hosted y gratuito (ya tenemos Docker)
- Sin limites de ejecuciones
- Podria orquestar flujos complejos Panel ↔ Moodle ↔ Zoho
- Considerar cuando la integracion bidireccional se vuelva compleja

## Precios Zoho CRM (referencia)

Para el nivel de personalizacion que necesitaria WebCurso, se requiere plan Enterprise (~27 EUR/usuario/mes) por las custom functions en Deluge. Esto refuerza la decision de mantener la logica en el Panel.

## SaaS comerciales del sector (competencia)

| Plataforma | Tipo | Notas |
|---|---|---|
| Gesforma | SaaS gestion FUNDAE | Precio opaco, modelo consultivo, soporte humano |
| iFormalia | Gestor formacion bonificada | Madrid, posicionado en el sector |
| Vertice eLearning | Proveedor contenido + LMS | Mas contenido que gestion, complementario |
| Conpas S-ERP | ERP formacion | Genera XML FUNDAE desde su BD |

Ninguno ofrece codigo abierto ni integracion con el ecosistema Zoho+Moodle que ya usa WebCurso.
