<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seguridad de la vía automática (Power Automate → correo → IMAP)
    |--------------------------------------------------------------------------
    | Power Automate incluye `token` dentro del JSON del correo. El comando
    | `encuestas-calidad:leer-imap` lo valida antes de procesar, para no dar
    | por buena una respuesta de un correo falso/spoofeado.
    */
    'token'              => env('ENCUESTA_CALIDAD_TOKEN'),
    'remitente_esperado' => env('ENCUESTA_CALIDAD_REMITENTE'), // p.ej. flujo@webcurso.onmicrosoft.com (opcional)

    /*
    |--------------------------------------------------------------------------
    | Lectura IMAP
    |--------------------------------------------------------------------------
    | `imap_enabled` protege el buzón real: en dev/staging por defecto está OFF
    | para no marcar como leídos correos reales de la cuenta Gmail.
    | `imap_account` es la clave de la cuenta en config/imap.php.
    */
    'imap_enabled' => env('ENCUESTA_CALIDAD_IMAP_ENABLED', env('APP_ENV') === 'production'),
    'imap_account' => env('ENCUESTA_CALIDAD_IMAP_ACCOUNT', 'encuestas'),
    'asunto_marca' => '[ENCUESTA-CALIDAD]',
    'marcador_inicio' => '===ENCUESTA-CALIDAD-JSON===',
    'marcador_fin'    => '===FIN===',

    /*
    |--------------------------------------------------------------------------
    | Resolución del curso vía Moodle (respaldo por webservice)
    |--------------------------------------------------------------------------
    | Cuando el Panel no encuentra el curso de una encuesta (ni por Nº Acción ni
    | por las 4 fuentes del alumno), se cruza el email contra el índice de
    | matrículas de Moodle (`moodle_matricula_index`, poblado por
    | `encuestas-calidad:snapshot-moodle`). Solo webservice, sin tocar la BD.
    | `moodle_margen_dias`: holgura al comparar la fecha de la encuesta con la
    | ventana [inicio, fin] del curso de Moodle.
    */
    'moodle_resolucion_enabled' => env('ENCUESTA_CALIDAD_MOODLE_RESOLUCION', true),
    'moodle_margen_dias'        => (int) env('ENCUESTA_CALIDAD_MOODLE_MARGEN_DIAS', 15),
    // Criterio principal: el curso cuyo último acceso del alumno cae a <= N días de
    // la fecha de la encuesta (la encuesta se rellena al terminar el curso).
    'moodle_ventana_acceso_dias' => (int) env('ENCUESTA_CALIDAD_MOODLE_VENTANA_ACCESO', 45),

    /*
    |--------------------------------------------------------------------------
    | Clasificación por tutor (profesor real del curso en Moodle)
    |--------------------------------------------------------------------------
    | El snapshot lee el profesor de cada curso (core_enrol_get_enrolled_users
    | withcapability moodle/course:update) y lo mapea por `moodle_username`.
    | Álvaro y Raquel COMPARTEN aula → a nivel de curso solo se distingue "David
    | Guerra" (aula propia) del bucket conjunto "Álvaro Pino / Raquel García".
    | `moodle_tutor_usernames`: username Moodle → nombre del tutor.
    | `moodle_tutor_label_compartida`: etiqueta cuando el aula es de Álvaro/Raquel.
    */
    'moodle_tutor_usernames' => [
        'tutorwebcurso@gmail.com' => 'David Guerra',
        'tutoralvarop'            => 'Álvaro Pino',
        'traquelg'                => 'Raquel García',
    ],
    // Tutores que comparten aula (no separables a nivel de curso) → etiqueta conjunta.
    'moodle_tutores_compartidos'    => ['Álvaro Pino', 'Raquel García'],
    'moodle_tutor_label_compartida' => 'Álvaro Pino / Raquel García',

    /*
    |--------------------------------------------------------------------------
    | Umbral de "alta puntuación"
    |--------------------------------------------------------------------------
    | Escala del Form: 1 (peor) .. 4 (mejor). Se considera "alta" >= este valor.
    */
    'umbral_alta' => (int) env('ENCUESTA_CALIDAD_UMBRAL_ALTA', 4),

    /*
    |--------------------------------------------------------------------------
    | Campos de identificación → alias de cabecera (import CSV/XLS)
    |--------------------------------------------------------------------------
    | La importación NO usa letras de columna fijas: localiza cada campo por el
    | nombre de su cabecera (normalizado: minúsculas, sin tildes, substring).
    | Añade aquí variantes si el export de Forms usa otro texto.
    */
    'campos' => [
        'hora_inicio'           => ['hora de inicio'],
        'hora_fin'              => ['hora de finalizacion', 'hora de fin'],
        'fecha_cumplimentacion' => ['fecha de cumplimentacion'],
        'alumno_nombre'         => ['nombre y apellido', 'nombre del alumno', 'nombre alumno'],
        'alumno_email'          => ['email alumno', 'correo del alumno', 'email del alumno', 'e-mail alumno'],
        'numero_accion'         => ['n accion', 'no accion', 'numero accion', 'nº accion', 'n° accion'],
        'numero_grupo'          => ['n grupo', 'no grupo', 'numero grupo', 'nº grupo', 'n° grupo'],
        'cif_empresa'           => ['cif empresa', 'cif de la empresa', 'cif'],
        'denominacion_accion'   => ['denominacion accion', 'denominacion de la accion', 'denominacion'],
        'modalidad'             => ['modalidad'],
        'edad_raw'              => ['edad'],
        'sexo'                  => ['sexo', 'genero'],
        'titulacion'            => ['titulacion', 'nivel de estudios', 'estudios'],
        'lugar_trabajo'         => ['lugar de trabajo', 'centro de trabajo'],
        'categoria_profesional' => ['categoria profesional', 'categoria'],
        // OJO: no usar el alias suelto 'horario' — matchearía las preguntas
        // "3.2 El horario ha favorecido...". Solo la cabecera específica.
        'horario_curso'         => ['horario del curso'],
        'porcentaje_jornada'    => ['% jornada', 'porcentaje jornada', 'jornada'],
        'tamano_empresa'        => ['tamano empresa', 'tamano de la empresa', 'plantilla'],
        'observaciones'         => ['sugerencia', 'observacion', 'observaciones', 'comentario'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Preguntas de valoración 1-4 → código de bloque FUNDAE
    |--------------------------------------------------------------------------
    | La importación localiza cada columna de valoración por el CÓDIGO con el que
    | empieza su cabecera (p.ej. "1.1 La organización..."). El item 10 (grado de
    | satisfacción general) se guarda además en `satisfaccion_general`.
    |
    | OJO: el Form duplica la pregunta 3.2 (quirk). La 1ª aparición va a item_05
    | y la 2ª a item_19 (el servicio detecta la repetición del código).
    |
    | ⚠️ Confirmar códigos/orden contra un export real antes de producción.
    */
    'preguntas' => [
        'item_01' => '1.1',
        'item_02' => '1.2',
        'item_03' => '2.1',
        'item_04' => '3.1',
        'item_05' => '3.2',
        'item_06' => '4.1',
        'item_07' => '4.2',
        'item_08' => '5.1',
        'item_09' => '5.2',
        'item_10' => '6.1',
        'item_11' => '6.2',
        'item_12' => '7.1',
        'item_13' => '7.2',
        'item_14' => '9.1',
        'item_15' => '9.2',
        'item_16' => '9.3',
        'item_17' => '9.4',
        'item_18' => '9.5',
        'item_19' => '3.2',  // segunda aparición (duplicado del Form)
    ],
    // Código de la pregunta 10 = satisfacción general (columna clave)
    'codigo_satisfaccion' => '10',

    /*
    |--------------------------------------------------------------------------
    | Bloques FUNDAE → items que promedian (para las medias por bloque)
    |--------------------------------------------------------------------------
    */
    'bloques' => [
        'organizacion'       => ['label' => 'Organización',          'items' => ['item_01', 'item_02']],
        'contenidos'         => ['label' => 'Contenidos',            'items' => ['item_03']],
        // item_19 es la 2ª aparición del 3.2 (duplicado del Form); se guarda pero
        // NO se promedia aquí para no sobre-ponderar la pregunta 3.2.
        'duracion_horario'   => ['label' => 'Duración / Horario',    'items' => ['item_04', 'item_05']],
        'tutores'            => ['label' => 'Tutores',               'items' => ['item_06', 'item_07']],
        'medios'             => ['label' => 'Medios didácticos',     'items' => ['item_08', 'item_09', 'item_10', 'item_11']],
        'teleformacion'      => ['label' => 'Teleformación',         'items' => ['item_12', 'item_13']],
        'valoracion_general' => ['label' => 'Valoración general',    'items' => ['item_14', 'item_15', 'item_16', 'item_17', 'item_18']],
        'satisfaccion'       => ['label' => 'Satisfacción general',  'items' => ['satisfaccion_general']],
    ],

    /*
    |--------------------------------------------------------------------------
    | Claves canónicas del JSON de Power Automate (vía IMAP)
    |--------------------------------------------------------------------------
    | Como el flujo lo montamos nosotros, el JSON usa directamente estas claves
    | (≈ campos del modelo). El comando IMAP mapea 1:1. Los items van como
    | item_01..item_19 y satisfaccion_general.
    */
    'json_keys_meta' => [
        'forms_id', 'alumno_nombre', 'alumno_email', 'numero_accion', 'numero_grupo',
        'cif_empresa', 'denominacion_accion', 'modalidad', 'fecha', 'observaciones',
    ],
];
