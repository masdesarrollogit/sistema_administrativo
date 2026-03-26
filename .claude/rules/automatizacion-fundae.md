# Automatizacion FUNDAE — Portal y XML

## Estado actual

Actualmente la interaccion con FUNDAE es manual:
1. El personal accede al portal web de FUNDAE con certificado digital
2. Descarga archivos XLS/CSV (empresas, grupos, participantes bonificados, acciones formativas)
3. Los importa al Panel via la seccion de Importar CSV/XLS

## Portales de FUNDAE

| Portal | URL | Funcion |
|---|---|---|
| Lanzadera | `empresas.fundae.es/Lanzadera` | Aplicacion principal: alta empresas, comunicacion inicio/fin grupos, participantes, credito, diplomas |
| Tramites | `tramites.fundae.es` | Gestiones complementarias: observaciones, documentacion, requerimientos |

- Acceso con **certificado digital** (persona fisica o juridica) o **Cl@ve**
- **No existe API REST publica ni web services**

## Automatizacion prevista con Playwright

Usar **Playwright** para automatizar la interaccion con el portal FUNDAE:

### Funciones de descarga (prioridad alta)
- Autenticarse en el portal FUNDAE
- Navegar a las secciones de descarga
- Descargar automaticamente archivos XLS/CSV actualizados (empresas, grupos, participantes)
- Disparar la importacion al Panel

### Funciones de comunicacion (prioridad media-alta)
- Comunicar inicio de grupos formativos
- Comunicar finalizacion de grupos formativos
- Registrar participantes

### Informacion necesaria para implementar
- Credenciales de acceso al portal FUNDAE (certificado digital)
- Estructura del portal (URLs, formularios, navegacion)
- Frecuencia de actualizacion deseada
- Manejo de captchas o verificaciones del portal (si las hay)
- Definir si se ejecuta como comando artisan, cron job, o manualmente

## Generacion de XML para cargas masivas (alto valor)

FUNDAE acepta cargas masivas mediante ficheros XML validados contra esquemas XSD publicados anualmente. Generar estos XML desde el Panel eliminaria la necesidad de introducir datos manualmente en el portal.

### Tipos de ficheros XML

| Fichero | Codigo | Contenido |
|---|---|---|
| **Acciones Formativas** | AF | Codigo, nombre, area profesional, modalidad, duracion, objetivos, contenido |
| **Inicio de Grupos Formativos** | IGF | Fechas, horarios, lugar, formador/tutor (NIF, email, telefono, horas), participantes (NIF, NISS, CCC, grupo cotizacion), centro de imparticion |
| **Finalizacion de Grupos Formativos** | FGF | Participantes finalizados, nivel de estudios, costes (directos, indirectos, organizacion), cuantia de bonificacion |

### Detalles tecnicos
- **Formato:** XML validado contra XSD
- **Esquemas XSD:** publicados en seccion "Descargas XML" de Lanzadera, actualizados cada ano
- **Variantes por perfil:** Bonificada, Organizadora, Grupo de Empresas
- **Novedad 2026:** Eliminacion del tipo de documento CIF (90), solo NIF (10)
- **Desde 2023:** datos de participantes incluidos en el XML de Finalizacion de Grupo (ya no hay XML separado)
- **Desde 2023:** FUNDAE recupera datos del participante de la TGSS automaticamente con solo el NIF

### Estructura real del XML de Inicio de Grupo (WebCurso)

Basado en el XML real que WebCurso sube a FUNDAE (`INICIO_GRUPO_BUENO_2026_david.xml`).

**Patron clave:** WebCurso crea **un grupo por participante** con fechas escalonadas (regla de no simultaneos).

```xml
<grupos>
  <grupo>
    <idAccion>241</idAccion>                    <!-- ID accion formativa en FUNDAE -->
    <idGrupo>3</idGrupo>                        <!-- ID grupo secuencial en FUNDAE -->
    <descripcion>{CIF} {empresa} {alumno} {curso} {horas}h {tramo}{tutor_inicial}</descripcion>
    <NumeroParticipante>1</NumeroParticipante>   <!-- Siempre 1 (un grupo por alumno) -->
    <fechaInicio>10/04/2026</fechaInicio>        <!-- dd/mm/yyyy -->
    <fechaFin>05/05/2026</fechaFin>
    <responsable>Alvaro Pino</responsable>       <!-- Personal WebCurso -->
    <telefonoContacto>601233530</telefonoContacto>
    <distanciaTeleformacion>
      <asistenciaTeleformacion>
        <centro>
          <cif>B65828857</cif>                   <!-- CIF entidad WebCurso -->
          <nombreCentro>MARKETING SOFTWARE 2012</nombreCentro>
          <direccionDetallada>CALLE ARIBAU 161, BARCELONA</direccionDetallada>
          <codPostal>08036</codPostal>
          <localidad>BARCELONA</localidad>
        </centro>
        <telefono>601233530</telefono>
      </asistenciaTeleformacion>
      <horario>
        <horaTotales>25</horaTotales>
        <horaInicioTramo2>15:00</horaInicioTramo2>  <!-- Tramo 1 u 2 segun tutor -->
        <horaFinTramo2>18:00</horaFinTramo2>
        <dias>LMXJV</dias>                          <!-- L=Lunes M=Martes X=Miercoles J=Jueves V=Viernes -->
      </horario>
      <Tutor>
        <numeroHoras>25</numeroHoras>
        <tipoDocumento>10</tipoDocumento>            <!-- 10=NIF (desde 2026, ya no se usa 90=CIF) -->
        <documento>46350455G</documento>
        <nombre>David</nombre>
        <apellido1>Guerra</apellido1>
        <apellido2>Murciano</apellido2>
        <telefono>601233530</telefono>
        <correoElectronico>tutorias@webcurso.es</correoElectronico>
        <tutoria>
          <tipoTutoria><tutorias>1</tutorias></tipoTutoria>  <!-- 1=tutorias estandar -->
          <descripcion>Informacion adicional</descripcion>
        </tutoria>
      </Tutor>
    </distanciaTeleformacion>
    <EmpresasParticipantes>
      <empresa>
        <cifEmpresaParticipante>B55154595</cifEmpresaParticipante>  <!-- CIF empresa cliente -->
        <jornadaLaboral>1</jornadaLaboral>                           <!-- 1=jornada completa -->
      </empresa>
    </EmpresasParticipantes>
    <observaciones></observaciones>
  </grupo>
</grupos>
```

### Diferencias con la plantilla FUNDAE

La plantilla oficial (`InicioGrupoEmpresas.xml`) incluye secciones que WebCurso **no usa**:
- `jornadaPresencial` — WebCurso solo ofrece teleformacion
- `aulaVirtual` — seccion para aulas virtuales con video
- `tipoDocumentoCentro` — WebCurso usa `cif` directamente
- `calendario` — fechas especificas de imparticion presencial
- Multiples tutores por grupo — WebCurso asigna 1 tutor por grupo
- Multiples empresas por grupo — WebCurso asigna 1 empresa por grupo

### Datos fijos de WebCurso (configurables en Panel)

| Dato | Valor actual | Donde configurar |
|---|---|---|
| Centro CIF | B65828857 | config/webcurso.php |
| Centro nombre | MARKETING SOFTWARE 2012 | config/webcurso.php |
| Centro direccion | CALLE ARIBAU 161, BARCELONA 08036 | config/webcurso.php |
| Telefono contacto | 601233530 | config/webcurso.php |
| Responsable | Alvaro Pino | config/webcurso.php |
| Dias | LMXJV | fijo (lunes a viernes) |
| tipoDocumento | 10 (NIF) | fijo desde 2026 |
| tipoTutoria | 1 | por defecto |

### Convencion de descripcion del grupo

Formato: `{CIF} {razon_social} {nombre_alumno} {nombre_curso} {horas}h {tramo}{inicial_tutor}`

Ejemplo: `B55154595 GITAXI SL Cristina Burgos Palacios Gestion del Estres 25h T2D`
- T2 = Tramo 2
- D = inicial del tutor (David)

### XML de alta de Acciones Formativas (AF)

El sistema debe permitir **crear acciones formativas nuevas** y generar el XML para cargar en FUNDAE.

**Ejemplo real:** `ACCIONES_2026_parte3.xml` (acciones 146-222)
**Plantilla XSD:** `AAFF_Inicio.xsd.xml`

**Estructura del XML de Acciones Formativas:**

```xml
<AccionesFormativas>
  <AccionFormativa>
    <codAccion>146</codAccion>                                    <!-- Numero Accion = idAccion -->
    <nombreAccion>Curso de canva e ia 80h m</nombreAccion>        <!-- Denominacion con horas y plataforma -->
    <codGrupoAccion>068-06</codGrupoAccion>                       <!-- Codigo grupo accion -->
    <codAreaProfesional>ADGG</codAreaProfesional>                  <!-- Area profesional (tabla en XSD) -->
    <modalidadTeleformacion>
      <horasTe>80</horasTe>                                       <!-- Horas teleformacion -->
    </modalidadTeleformacion>
    <cifPlataforma>B65828857</cifPlataforma>                      <!-- CIF plataforma educativa -->
    <razonSocialPlataforma>MARKETING SOFTWARE 2012</razonSocialPlataforma>
    <uri>aula.1curso.com</uri>                                    <!-- URL plataforma -->
    <usuario>supervisortripartita@webcurso.es</usuario>           <!-- Credencial supervision FUNDAE -->
    <password>Tr1part1ta4444*</password>                          <!-- Credencial supervision FUNDAE -->
    <objetivos>Texto libre con objetivos del curso</objetivos>
    <contenidos>Texto libre con contenido detallado</contenidos>  <!-- Muy extenso -->
  </AccionFormativa>
</AccionesFormativas>
```

**Campos clave del XSD:**
- `codAccion`: max 5 digitos (patron: [0-9]{1,5})
- `nombreAccion`: max 255 caracteres
- `codGrupoAccion`: formato XXX-XX (patron: [0-9]{3}-[0-9]{2})
- `codAreaProfesional`: 4 caracteres (codigo del area, ej: ADGG, ADGN, IFCD...)
- Modalidad: choice entre `modalidadPresencial`, `modalidadTeleformacion`, `modalidadMixta`
- `cifPlataforma`: 9 caracteres
- `objetivos` y `contenidos`: texto libre sin limite
- `empParticipantes` (opcional): CIF empresa + info RLT

**Datos de las dos plataformas de WebCurso:**

| Plataforma | CIF | Razon Social | URI | Usuario | Codigo |
|---|---|---|---|---|---|
| Principal (m) | B65828857 | MARKETING SOFTWARE 2012 | aula.1curso.com | supervisortripartita@webcurso.es | m |
| Secundaria (a) | B41273392 | System Centros de Formacion, S.L. | www.plataformateleformacion.com | Smarkesoft | a |

**codGrupoAccion:** En todos los ejemplos de WebCurso es "068-06".

### AccionesFormativas(2026).xls — Estructura del archivo de FUNDAE

Columnas: A=Numero Accion | B=Denominacion | C=Modalidad | D=Tipo | E=Estado | F=Horas | G=Nivel Formacion | H=NIF Proveedor Plataforma | I=URL | J=Clave Acceso | K=Usuario | L=Area Profesional | M=Codigo Actividad

- 244 acciones formativas (2026)
- La letra al final de la denominacion (ej: "60h m") es control interno WebCurso: "m" = aula.1curso.com, "a" = plataformateleformacion.com
- Las columnas J (clave acceso) y K (usuario) son credenciales de supervision FUNDAE — NO mostrar publicamente
- Se actualiza periodicamente (no solo una vez al ano)
- Importacion: UPSERT por numero_accion (no truncar, tiene relaciones)

### Vinculacion Acciones Formativas ↔ Cursos Moodle

- 1 accion formativa FUNDAE → N cursos en Moodle (1 por tutor + repasos + desactualizados)
- Campo `idnumber` de Moodle usado para vincular: formato `AF-{numero_accion}-{variante}` (P=plantilla, XX=iniciales tutor, R=repaso)
- Moodle NO permite idnumber duplicado (validacion a nivel de aplicacion)
- Tabla pivot en el Panel: `accion_formativa_moodle_curso` con tipo (plantilla, tutor, repaso, desactualizado) y tutor_id

### Estructura real del XML de Finalizacion de Grupo (Organizadora)

Plantilla FUNDAE: `FinGrupoOrganizadora.xml`
Esquema XSD: `FinalizacionGrupo_Organizadora.xsd`
Archivos en: `storage/fundae/xsd/` y `storage/fundae/ejemplos/`

```xml
<grupos>
  <grupo>
    <idAccion>00002</idAccion>                          <!-- ID accion formativa -->
    <idGrupo>00001</idGrupo>                            <!-- ID grupo -->
    <participantes>
      <participante>
        <nif>00000001R</nif>                            <!-- NIF del participante -->
        <N_TIPO_DOCUMENTO>10</N_TIPO_DOCUMENTO>         <!-- 10=NIF, 20=Pasaporte, 60=NIE -->
        <ERTE_RD_ley>true</ERTE_RD_ley>                <!-- Afectado por ERTE (opcional) -->
        <email>mail@fundae.es</email>
        <telefono>666666661</telefono>
        <discapacidad>false</discapacidad>              <!-- (opcional) -->
        <afectadosTerrorismo>false</afectadosTerrorismo>
        <afectadosViolenciaGenero>false</afectadosViolenciaGenero>
        <categoriaprofesional>3</categoriaprofesional>  <!-- 1=Directivo, 2=Mando intermedio, 3=Tecnico, 4=Cualificado, 5=Baja cualificacion -->
        <nivelestudios>4</nivelestudios>                <!-- 1-10 (ver XSD para detalle) -->
        <DiplomaAcreditativo>N</DiplomaAcreditativo>    <!-- S/N -->
        <fijoDiscontinuo>true</fijoDiscontinuo>         <!-- (opcional) -->
      </participante>
    </participantes>
    <costes>
      <coste>
        <cifagrupada>B00000000</cifagrupada>            <!-- CIF empresa agrupada -->
        <directos>1500</directos>                       <!-- Costes directos -->
        <indirectos>2000</indirectos>                   <!-- Costes indirectos (max 10%) -->
        <organizacion>500</organizacion>                <!-- Costes organizacion (max 10-20%) -->
        <salariales>1500</salariales>                   <!-- Costes salariales -->
        <periodos>                                      <!-- Periodos de bonificacion (opcional) -->
          <periodo>
            <mes>10</mes>                               <!-- Mes de aplicacion (1-12) -->
            <importe>900</importe>                      <!-- Importe a bonificar -->
          </periodo>
        </periodos>
      </coste>
    </costes>
  </grupo>
</grupos>
```

**Campos del participante en finalizacion:**
- `categoriaprofesional`: 1=Directivo, 2=Mando intermedio, 3=Tecnico, 4=Cualificado, 5=Baja cualificacion
- `nivelestudios`: 1=Menos que primaria, 2=Primaria, 3=ESO/EGB, 4=Bachillerato/FP medio, 5=Cert. prof. nivel 3, 6=FP superior, 7=Diplomatura/Grado, 8=Licenciatura/Master, 9=Doctorado, 10=Otras
- `N_TIPO_DOCUMENTO`: 10=NIF, 20=Pasaporte, 60=NIE
- `DiplomaAcreditativo`: S=Si, N=No

**Nota:** La finalizacion agrupa costes por empresa (cifagrupada) y permite distribuir la bonificacion en periodos mensuales.

### Implementacion sugerida
- Almacenar la plantilla XSD anual de FUNDAE en `storage/fundae/xsd/`
- Crear servicio Laravel `FundaeXmlService` que genere XML validos desde los datos del Panel
- Los datos fijos del centro se configuran en `config/webcurso.php`
- Validar contra XSD antes de guardar/enviar
- Comando artisan para generar XML por accion formativa (genera todos los grupos de esa accion)
- Opcion futura: subir XML al portal via Playwright
