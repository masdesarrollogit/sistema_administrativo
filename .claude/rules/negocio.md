# Modelo de negocio — WebCurso

## Quienes somos

WebCurso es una empresa española que ofrece cursos online bonificados por FUNDAE (Fundacion Estatal para la Formacion en el Empleo) a empresas con empleados en regimen general de la Seguridad Social.

Somos **empresa organizadora**: nos encargamos de organizar la formacion y gestionar las bonificaciones ante FUNDAE para la mayoria de nuestros clientes.

## Modalidad de formacion

WebCurso **solo ofrece teleformacion** (formacion online). No ofrece modalidad presencial ni mixta. Esto es una premisa global del proyecto que afecta a todos los modulos.

## Modelo de negocio

1. **Captacion**: atraemos empresas a traves de la web (webcurso.es), callcenter y campanas de email marketing (Zoho Campaigns / Sendy)
2. **Oferta**: ofrecemos cursos online bonificados. Las empresas pueden formarlos sin coste directo, usando su credito de formacion FUNDAE (derivado de la cotizacion a la Seguridad Social)
3. **Verificacion de saldo**: se verifica el credito FUNDAE disponible de la empresa antes de cerrar acuerdos
4. **Gestion administrativa**: gestionamos todo el proceso — desde la documentacion inicial hasta la bonificacion en FUNDAE
5. **Formacion**: los alumnos realizan los cursos en nuestra plataforma Moodle
6. **Bonificacion**: gestionamos la justificacion y aplicacion de la bonificacion ante FUNDAE

## Tutores

- Cada grupo formativo tiene un tutor asignado
- **Tipos:** internos (personal WebCurso) y externos
- **Horarios por tramos:** Tramo 1 (8:00-11:00) o Tramo 2 (15:00-18:00). Un solo tramo por tutor
- **Limite FUNDAE:** maximo 80 participantes por tutor en teleformacion
- **Email de tutorias:** tutorias@webcurso.es (usado en XML FUNDAE)
- **En Moodle:** cada tutor tiene su propia copia del curso (se duplica el curso base y se asigna al tutor)

## Plataformas educativas

WebCurso opera con dos plataformas LMS:
- **aula.1curso.com** (codigo "m") — plataforma principal. La API del Panel se conecta aqui.
- **www.plataformateleformacion.com** (codigo "a") — plataforma secundaria.
- En la denominacion de acciones formativas FUNDAE, la letra final indica la plataforma (ej: "Excel 365 avanzado 60h m")

## Operativa de cursos en Moodle

- Se duplica el curso base y se asigna al tutor → cada tutor tiene su propia copia del curso
- **No se usan cohortes** → alumnos se matriculan directamente en el aula del tutor correspondiente
- **Cursos desactualizados:** no se borran (auditoría FUNDAE), se les agrega "(Desactualizado)" al nombre
- **Cursos de repaso:** se duplica el curso y se le agrega "(REPASO)", categoría Repaso
- **Un alumno no puede hacer dos cursos simultáneamente** (restricción por alumno, no por candidato/empresa)

## Datos de la entidad (centro de formacion)

- **CIF:** B65828857
- **Nombre:** MARKETING SOFTWARE 2012
- **Direccion:** CALLE ARIBAU 161, BARCELONA 08036
- **Responsable:** Alvaro Pino
- **Telefono:** 601233530
- Estos datos se usan en los XML de comunicacion a FUNDAE

## Tipos de cliente

| Tipo | Descripcion | Quien gestiona la bonificacion |
|---|---|---|
| **Empresa organizadora** | Cliente estandar | WebCurso gestiona todo ante FUNDAE |
| **Empresa externa** | Cliente que gestiona su propia bonificacion | El propio cliente gestiona ante FUNDAE |

## Que es el Panel

El **Panel** (esta aplicacion) es la herramienta interna de WebCurso para automatizar los procesos administrativos que hasta ahora se llevaban en hojas de Excel y herramientas dispersas.

**El Panel NO es:**
- Un CRM (eso es Zoho CRM)
- Un sistema de facturacion (eso es Zoho Books)
- Una plataforma de aprendizaje (eso es Moodle)

**El Panel SI es:**
- La capa de administracion propia que consolida y automatiza los procesos no cubiertos por las herramientas existentes
- El nexo que conectara (en el futuro) con Zoho CRM, Zoho Books y Moodle

## Objetivo a largo plazo

Automatizar completamente el flujo administrativo desde que un cliente confirma interes hasta que sus empleados estan matriculados, formados y la bonificacion esta gestionada. Integracion futura con Zoho CRM para evitar doble entrada de datos.
