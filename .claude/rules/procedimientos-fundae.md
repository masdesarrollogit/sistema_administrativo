# Procedimientos administrativos FUNDAE

Flujo completo que una empresa organizadora debe seguir ante FUNDAE para formacion bonificada. Basado en investigacion de marzo 2026.

## Flujo administrativo completo (8 fases)

### Fase 1: Calculo del credito formativo
- Credito anual = cuota de formacion profesional = salarios brutos anuales x 0,70%
- Porcentajes de bonificacion segun tamano de empresa:
  - 1 a 5 trabajadores: credito minimo de **420 EUR**
  - 6 a 9: **100%**
  - 10 a 49: **75%**
  - 50 a 249: **60%**
  - 250+: **50%**
- Empresas con menos de 50 trabajadores pueden acumular credito no utilizado durante 2 ejercicios (marcandolo antes del 30 de junio)

### Fase 2: Alta y registro en la aplicacion FUNDAE
- Acceso con **certificado digital** (persona fisica o juridica) o **Cl@ve**
- Registro con perfil de "Entidad Organizadora"
- Firma de **contrato de encomienda** entre empresa bonificada y organizadora (modelo disponible en FUNDAE)
- Perfiles disponibles: Bonificada, Entidad Organizadora, Gestor Administrativo, Grupo de Empresas

### Fase 3: Informacion a la RLT (Representacion Legal de los Trabajadores)
- **Obligatorio** informar a la RLT **antes** de comunicar el inicio de la formacion
- Contenido: denominacion del curso, objetivos, colectivos destinatarios, calendario, metodos pedagogicos, criterios de seleccion, lugar, balance del ejercicio anterior
- La RLT tiene **15 dias habiles** para responder
- Si hay desacuerdo: segundo plazo de 15 dias, luego informe de discrepancia para mediacion sectorial

### Fase 4: Comunicacion de inicio del grupo formativo
- Comunicar la **accion formativa** (modalidad, duracion, objetivos, contenido)
- Comunicar el **inicio del grupo** con minimo **2 dias naturales** de antelacion
- Los centros de formacion deben estar registrados mediante **declaracion responsable**
- **Datos del grupo:** fechas, horarios, lugar, formador/tutor, participantes

### Fase 5: Modificaciones tras la comunicacion
- Cancelacion, cambio de horario/fechas/lugar: **1 dia natural** de antelacion
- Cambio de fecha de inicio: minimo **2 dias naturales** entre comunicacion de modificacion y nuevo inicio
- Cambios durante la imparticion: comunicacion de incidencia con justificacion

### Fase 6: Realizacion de la formacion
- Formacion **gratuita** para los participantes
- Duracion minima: **2 horas**; maxima por dia: **8 horas** (salvo formacion de un solo dia)
- Tamano maximo de grupo: **30 presencial** (25 para certificados de profesionalidad), **80 por tutor en teleformacion**
- **Requisitos de teleformacion (relevantes para Moodle):**
  - Plataforma LMS con trazabilidad (acceso secuencial a contenidos)
  - Registros de interaccion, tiempos de conexion, controles de aprendizaje
  - Herramientas de comunicacion sincronas y asincronas
  - Acceso para organos de control mediante usuario especifico
  - Disponibilidad 24x7, accesibilidad, backup periodico
  - Participante debe completar minimo **75% de los controles de aprendizaje**
  - El tiempo de conexion ya NO es requisito obligatorio (sentencia judicial), aunque se recomienda >50-55%
- Cuestionario de evaluacion de calidad FUNDAE al finalizar

### Fase 7: Comunicacion de finalizacion
- Comunicar a traves de la aplicacion **antes de presentar el boletin de cotizacion de diciembre**
- Datos a comunicar:
  - Participantes finalizados (completaron >= 75% controles)
  - Costes: directos, indirectos (max 10%), organizacion (max 10%; 20% para 1-5 trab.; 15% para 6-9)
  - Cuantia y mes de aplicacion de la bonificacion
- Desde 2023: datos del participante se recuperan automaticamente de la TGSS con solo el NIF
- Errores corregibles hasta el dia 20 del mes siguiente
- Importe maximo bonificable = menor de: credito disponible, costes reales, o modulos economicos (**12 EUR/hora presencial, 7 EUR/hora teleformacion**)

### Fase 8: Aplicacion de la bonificacion
- Se aplica en los **recibos de liquidacion de cotizaciones a la SS** via **Sistema RED**
- Casilla **763 - bonificacion formacion continua**
- Plazo: desde comunicacion de finalizacion hasta el boletin de **diciembre del ejercicio en curso**
- Los pagos de facturas deben realizarse antes del ultimo dia habil del boletin de diciembre

---

## Datos requeridos por participante

Datos que FUNDAE exige al comunicar el inicio de un grupo formativo:

| Campo | Tipo | Detalle |
|---|---|---|
| NIF o NIE | Texto | Identificacion del participante |
| Nombre y apellidos | Texto | Completo |
| NISS | Numerico, 12 digitos | Numero de Identificacion de la Seguridad Social |
| CCC | Numerico, 11 digitos | Cuenta de Cotizacion de la empresa |
| Grupo de cotizacion TGSS | Codigo | Grupo de cotizacion del trabajador |
| Fecha de nacimiento | Fecha | dd/mm/YYYY |
| Sexo | H/M | Hombre/Mujer |
| Nivel de estudios | Codigo | Segun tabla FUNDAE |
| Categoria profesional | Codigo | Segun tabla FUNDAE |

**Nota:** Desde 2023, FUNDAE recupera automaticamente algunos datos (nombre, CIF empresa, fecha nacimiento, sexo, grupo cotizacion) de la TGSS con solo el NIF. Aun asi, conviene capturarlos en el Panel para validacion y para los XML.

---

## Herramientas digitales de FUNDAE

### Aplicacion "Lanzadera"
- **URL:** `empresas.fundae.es/Lanzadera`
- Aplicacion telematica principal para toda la gestion
- Funciones: alta de empresas, comunicacion de inicio/fin de grupos, gestion de participantes, consulta de credito, descarga de diplomas
- Acceso con certificado digital o Cl@ve

### Portal de Tramites
- **URL:** `tramites.fundae.es`
- Portal complementario para gestiones administrativas (observaciones, documentacion, requerimientos)

### Sistema de cargas masivas via XML

FUNDAE publica anualmente los formatos XML y esquemas XSD para cargas masivas. **3 tipos principales:**

| Fichero | Contenido |
|---|---|
| **Acciones Formativas (AF)** | Codigo, nombre, area profesional, modalidad, duracion, objetivos, contenido |
| **Inicio de Grupos Formativos (IGF)** | Fechas, horarios, lugar, formador/tutor (NIF, email, telefono, horas), participantes, centro de imparticion |
| **Finalizacion de Grupos Formativos (FGF)** | Participantes finalizados, nivel de estudios, costes (directos, indirectos, organizacion), cuantia de bonificacion |

- **Formato:** XML validado contra esquemas XSD publicados anualmente
- **Descarga de esquemas:** Seccion "Descargas XML" en Lanzadera
- **Variantes por perfil:** Bonificada, Organizadora, Grupo de Empresas
- **Novedad 2026:** Eliminacion del tipo de documento CIF (90), solo se usa NIF (10)
- **Desde 2023:** Los datos de participantes van incluidos en el XML de Finalizacion de Grupo (ya no hay XML separado)

### API / Web Services
- **FUNDAE NO ofrece API REST publica ni web services**
- Todo se gestiona via aplicacion web o carga de ficheros XML
- La unica via de automatizacion real es interactuar con el portal web programaticamente (Playwright)

---

## Normativa principal

| Norma | Contenido |
|---|---|
| **Ley 30/2015** | Ley marco del Sistema de Formacion Profesional para el Empleo |
| **RD 694/2017** | Desarrollo reglamentario de la Ley 30/2015 |
| **RD 1189/2025** | Modificacion del RD 694/2017 (en vigor desde 01/01/2026) |
| **Resolucion SEPE 25/11/2025** | Medidas especificas para formacion programada ejercicio 2026 |

### Cambios clave del RD 1189/2025

1. **Identificacion contable obligatoria:** costes de formacion bonificada en contabilidad diferenciada
2. **Conservacion documental:** minimo 4 anos
3. **Eliminacion de la "doble via":** sin tramite de audiencia previo ante FUNDAE; irregularidades van directamente a Inspeccion de Trabajo
4. **Control reforzado:** al menos 10% de los recursos publicos
5. **Devolucion voluntaria:** via expresa para regularizar incidencias

---

## Pain points comunes de empresas organizadoras

1. Inestabilidad del aplicativo FUNDAE (colapsos frecuentes)
2. Plazos inflexibles sin plan de contingencia cuando el portal cae
3. Incremento de controles e inspecciones (especialmente teleformacion)
4. Enorme carga documental (contrato, RLT, asistencia, cuestionarios, diplomas, registros, facturas — todo 4 anos)
5. Requisito del 75% en teleformacion (dificil acreditar estudio offline)
6. Complejidad normativa y cambios anuales en formatos XML
7. Responsabilidad subsidiaria de la organizadora
8. Ausencia de API de integracion (todo manual o semi-manual)

---

## Relevancia para el Panel de WebCurso

- **Matriculacion:** debe capturar los datos FUNDAE del participante (NIF, NISS, CCC, grupo cotizacion, etc.)
- **Importacion:** los XLS/CSV descargados de FUNDAE alimentan las tablas del Panel
- **Generacion de XML:** funcionalidad de alto valor — generar XML conformes a XSD para comunicaciones de inicio y fin
- **Automatizacion (Playwright):** unica via de integracion directa con el portal FUNDAE
- **Documentacion justificativa:** el Panel deberia facilitar la generacion y conservacion de documentos requeridos
