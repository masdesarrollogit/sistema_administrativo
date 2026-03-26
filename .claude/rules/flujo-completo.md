# Flujo completo de negocio

Descripcion del proceso de negocio de WebCurso de principio a fin. Cada fase indica si esta desarrollada en el Panel o pendiente.

## Fase 1 — Captacion *(fuera del Panel)*

- El prospecto contacta a WebCurso o es contactado (web, callcenter, campanas)
- La comunicacion y seguimiento se gestionan en **Zoho CRM**
- Cuando el cliente confirma interes en realizar cursos, pasa al Panel

**Estado:** fuera del alcance del Panel. Gestionado en Zoho CRM.

---

## Fase 2 — Creacion del candidato ✅ Desarrollado

- El personal administrativo de WebCurso crea un **Candidato** en el Panel
- Se asigna un **TipoCandidato** segun quien gestiona la bonificacion:
  - `empresa_organizadora` — WebCurso gestiona la bonificacion
  - `empresa_externa` — el cliente gestiona su propia bonificacion
  - `particular` — persona individual
- Se vincula a una **Empresa** (si organizadora) o **EmpresaExterna** (si externa)
- Se inicializan automaticamente los **Requisitos** obligatorios segun el tipo de candidato
- Se configuran los parametros de recordatorio: fecha de inicio, frecuencia de envio

**Componentes:** CandidatoForm, CandidatosIndex

---

## Fase 3 — Gestion de requisitos ✅ Desarrollado

- El candidato recibe **recordatorios automaticos** por email (cron diario 09:00 Madrid)
- Cada email incluye los requisitos faltantes y archivos adjuntos relevantes
- El candidato envia documentos/informacion al equipo de WebCurso
- El administrativo marca los requisitos como completados en el Panel
- Los requisitos tienen 3 estados: `pendiente` → `en_proceso` → `completado`
- Cuando todos los requisitos obligatorios se completan → candidato pasa a estado **"completo"**
- Si se alcanza el maximo de recordatorios → candidato se **pausa** automaticamente
- El campo **observacion** es de uso interno y NO se incluye en los correos al candidato
- El campo **descripcion personalizada** SI se incluye en los emails

**Componentes:** CandidatoEstatus, EnviarRecordatoriosCandidatos (comando)
**Emails:** RecordatorioRequisitosMail, ResumenPendientesAdminMail (resumen diario 13:00 para admin)

---

## Fase 4 — Matriculacion 🔧 Parcialmente desarrollado

Cuando el candidato tiene todos los requisitos completos, aparece la seccion "Matriculacion" en CandidatoEstatus:

### Flujo implementado:
1. **Crear grupo formativo**: seleccionar accion formativa, tutor, tramo horario, empresa, fechas inicio/fin, jornada laboral
2. **Agregar alumnos al grupo**: ingreso manual (nombre, apellidos, NIF, email). Alumnos asociados a la empresa, reutilizables
3. **Asignar ID grupo FUNDAE**: secuencial por accion (consulta tabla `grupos` importada + `grupos_formativos` del Panel)
4. **Generar XML de Inicio de Grupo**: descarga XML conforme al formato real de WebCurso
5. **Subir XML a FUNDAE**: manual (el admin sube el XML al portal de FUNDAE)
6. **Matricular**:
   - *Plataforma Moodle (codigo 'm')*: autodetecta el aula por el moodle_username del tutor → crea grupo Moodle → crea/actualiza usuarios → matricula con fechas → notifica al alumno por email
   - *Plataforma Aulasystem (codigo 'a')*: boton "Matriculado en Aulasystem" — marca manualmente, sin API disponible
7. **Notificar al alumno** (solo Moodle): email con usuario (=email), contrasena (`ucfirst(nombre)+'4444*'`), URL completa del curso, fechas

### Reglas de negocio:
- Un alumno NO puede estar en dos grupos con **fechas solapadas** (los grupos consecutivos de la misma accion estan permitidos)
- Un grupo permanece abierto hasta 2 dias antes de la fecha de inicio
- Limite FUNDAE: 80 alumnos por tutor por tramo
- Un grupo tiene: una accion formativa, un tutor, un tramo, una empresa participante, uno o mas alumnos
- Descripcion del grupo: `{CIF} {empresa} {alumno|({N})} {curso} {horas}h {tramo}{tutor_iniciales}`
- id_grupo_fundae se asigna automaticamente al crear el grupo

### Pendiente:
- Importacion de alumnos via XLSX (plantilla que se envia al candidato)
- Reorganizacion de categorias en Moodle

**Componentes:** MatriculacionPanel (anidado en CandidatoEstatus), TutoresIndex, AccionesFormativasIndex
**Modelos:** GrupoFormativo, Tutor, Alumno, AccionFormativa, AccionFormativaMoodleCurso
**Servicios:** FundaeXmlService, MoodleService (funcional)
**Emails:** CredencialesMoodleMail

---

## Fase 5 — Seguimiento academico ⚠️ Pendiente

- Control de progreso/asistencia del alumno en Moodle
- Comunicacion con alumnos durante el curso
- Posible integracion con API de notas de Moodle (getUserGrades ya existe en MoodleService)

---

## Fase 6 — Facturacion ⚠️ Pendiente

- Emision de factura al cliente
- Se usara **Zoho Books** (integracion futura)

---

## Fase 7 — Cierre FUNDAE ⚠️ Pendiente

- Solo aplica si WebCurso es empresa organizadora
- Justificacion de la formacion ante FUNDAE
- Aplicacion de la bonificacion en las cotizaciones
- Posible automatizacion futura con Playwright para interactuar con el portal FUNDAE
