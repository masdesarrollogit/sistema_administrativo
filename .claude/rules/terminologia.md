# Terminologia del dominio

Glosario de terminos usados en el codigo y en el negocio de WebCurso.

## Entidades principales

| Termino | Definicion |
|---|---|
| **Candidato** | Persona de contacto en la empresa cliente (ej: responsable de RRHH) que inicia el proceso de contratacion de cursos. Puede ser tambien el propio alumno. No confundir con "prospecto" (que esta en Zoho CRM). |
| **Alumno** | Empleado del cliente (o el propio candidato) que realizara el curso en Moodle. Un candidato puede tener uno o varios alumnos asociados. |
| **Empresa** | Empresa cliente registrada en FUNDAE. Identificada por CIF. Contiene datos de credito formativo, plantilla media, estado de bonificacion. |
| **Empresa externa** | Empresa cliente que gestiona su propia bonificacion ante FUNDAE (no la gestiona WebCurso). |
| **Requisito** | Documento o paso que el candidato debe completar antes de poder efectuar la matricula. Pueden ser obligatorios o no. Estados: pendiente, en_proceso, completado. |
| **Tipo de candidato** | Clasificacion del candidato que determina que requisitos se le asignan. Codigos: `empresa_organizadora`, `empresa_externa`, `particular`. |

## Proceso formativo

| Termino | Definicion |
|---|---|
| **Grupo formativo** | Entidad central de matriculacion en el Panel. Vincula una accion formativa, un tutor, una empresa participante, un tramo horario, fechas y uno o mas alumnos. Cada grupo tiene un id_grupo_fundae secuencial por accion. Estados: `abierto`, `comunicado`, `en_curso`, `completado`, `cancelado`. Modelo: `GrupoFormativo`. |
| **Matricula** | Proceso de dar de alta al alumno en un curso en Moodle. Se ejecuta desde un grupo formativo que ya tiene alumnos asignados. No es un modelo en la BD, es una accion que se ejecuta sobre el grupo. |
| **Accion formativa** | Denominacion oficial FUNDAE para un curso o programa formativo. Tiene un codigo unico (`numero_accion`) en FUNDAE. Se importa desde el XLS de AccionesFormativas. Modelo: `AccionFormativa`. |
| **Grupo (importado)** | Registro importado del XLS de grupos descargado de FUNDAE (tabla `grupos`). Contiene el historico de grupos ya comunicados. Se usa para determinar el siguiente `id_grupo_fundae` secuencial. NO confundir con GrupoFormativo (creado en el Panel). |
| **Participante bonificado** | Empleado inscrito en FUNDAE para recibir formacion bonificada. Sus datos se importan desde archivos XLS descargados del portal FUNDAE. |
| **Tutor** | Formador asignado a un grupo formativo. Puede ser interno (WebCurso) o externo. El tramo horario NO pertenece al tutor sino al grupo. Limite FUNDAE: 80 alumnos por tutor por tramo en teleformacion. |

## FUNDAE y bonificacion

| Termino | Definicion |
|---|---|
| **FUNDAE** | Fundacion Estatal para la Formacion en el Empleo. Organismo publico espanol que gestiona los fondos de formacion para trabajadores. Web: fundae.es |
| **Bonificacion** | Mecanismo por el cual las empresas recuperan el coste de la formacion mediante descuentos en sus cotizaciones a la Seguridad Social. |
| **Regimen general** | Tipo de afiliacion a la Seguridad Social que da derecho a formacion bonificada. Requisito para que los empleados puedan participar. |
| **Credito formativo** | Presupuesto anual que cada empresa tiene disponible para formacion bonificada. Depende de su plantilla media y cotizacion. |
| **Expediente** | Identificador de empresa en el sistema FUNDAE. |
| **Cofinanciacion privada** | Porcentaje del coste formativo que la empresa debe asumir directamente (no bonificable). |

## Identificadores clave

| Termino | Definicion |
|---|---|
| **CIF** | Numero de Identificacion Fiscal de la empresa. Es la clave de referencia cruzada entre todos los sistemas (Panel, FUNDAE, Zoho). |
| **NIF** | Numero de Identificacion Fiscal de persona fisica. Identifica a participantes/alumnos. |
| **NISS** | Numero de la Seguridad Social del participante. |
| **TGSS** | Tesoreria General de la Seguridad Social. Codigo de cuenta de cotizacion de la empresa. |
| **CNAE** | Clasificacion Nacional de Actividades Economicas. Codigo de actividad de la empresa. |
| **PIF** | Codigo de Plan Individual de Formacion en FUNDAE. |

## Terminos internos del Panel

| Termino | Definicion |
|---|---|
| **Panel** | Esta aplicacion. Nombre interno del proyecto de software. |
| **Empresa organizadora** (en contexto Panel) | Indica que WebCurso gestiona la bonificacion FUNDAE del cliente. |
| **Empresa externa** (en contexto Panel) | Indica que el cliente gestiona su propia bonificacion FUNDAE. |
| **Observacion** | Campo interno del candidato. Solo para uso del equipo administrativo de WebCurso. NO se incluye en correos al candidato. |
| **Descripcion personalizada** | Texto configurable por candidato que se incluye en los emails de recordatorio. |
