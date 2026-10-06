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

    // Hora del snapshot diario del índice de matrículas Moodle (routes/console.php)
    'snapshot_moodle_hora' => env('ENCUESTA_CALIDAD_SNAPSHOT_HORA', '03:30'),

    /*
    |--------------------------------------------------------------------------
    | Tercera vía: plugin de Moodle mod_calidadfundae (cuestionario íntegro)
    |--------------------------------------------------------------------------
    | El comando `encuestas-calidad:sincronizar-moodle` lee las respuestas por el
    | webservice `mod_calidadfundae_get_responses` (incremental por timemodified)
    | y las guarda con `origen = 'moodle_plugin'`.
    | `plugin_sync_enabled`: OFF fuera de producción, igual que la lectura IMAP.
    */
    'plugin_sync_enabled' => env('ENCUESTA_CALIDAD_PLUGIN_SYNC', env('APP_ENV') === 'production'),
    'plugin_page_size'    => (int) env('ENCUESTA_CALIDAD_PLUGIN_PAGE', 200),
    // Solape al reanudar: se relee este margen por si una respuesta se guardó en
    // el mismo segundo que el corte de la corrida anterior (idempotente).
    'plugin_solape_segundos' => 60,

    // Orígenes que traen el cuestionario FUNDAE íntegro (scopeCuestionarioCompleto)
    'origenes_cuestionario_completo' => ['moodle_plugin'],

    // Columna del plugin (escala 1-4) → columna del Panel. Las que el Form ya
    // preguntaba van a su item_NN de siempre para que las medias sigan siendo
    // comparables; las nuevas a item_20..22. v4_*f = Formadores, v4_*t = Tutores.
    'plugin_mapa' => [
        'v1_1'  => 'item_01', 'v1_2'  => 'item_02',
        'v2_1'  => 'item_03', 'v2_2'  => 'item_20',
        'v3_1'  => 'item_04', 'v3_2'  => 'item_05',
        'v4_1f' => 'item_06', 'v4_2f' => 'item_07',
        'v4_1t' => 'item_21', 'v4_2t' => 'item_22',
        'v5_1'  => 'item_08', 'v5_2'  => 'item_09',
        'v6_1'  => 'item_10', 'v6_2'  => 'item_11',
        'v7_1'  => 'item_12', 'v7_2'  => 'item_13',
        'v9_1'  => 'item_14', 'v9_2'  => 'item_15', 'v9_3' => 'item_16', 'v9_4' => 'item_17', 'v9_5' => 'item_18',
        'v10'   => 'satisfaccion_general',
    ],
    // Dicotómicos del plugin (1 = Sí, 2 = No) → columnas sino_* (nunca se promedian)
    'plugin_mapa_sino' => [
        's8_1'  => 'sino_pruebas_evaluacion',
        's8_2'  => 'sino_acreditacion',
        's10_1' => 'sino_recomendaria',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cuestionario íntegro: catálogo de preguntas para la pestaña "Cuestionario completo"
    |--------------------------------------------------------------------------
    | Orden y textos del cuestionario oficial (mismos que el plugin). `tipo`:
    | 'escala' (1-4, se promedia) | 'sino' (porcentaje Sí/No, NUNCA se promedia).
    */
    'cuestionario_completo' => [
        ['bloque' => '1. Organización del curso', 'preguntas' => [
            ['col' => 'item_01', 'codigo' => '1.1', 'tipo' => 'escala', 'texto' => 'El curso ha estado bien organizado'],
            ['col' => 'item_02', 'codigo' => '1.2', 'tipo' => 'escala', 'texto' => 'El número de alumnos del grupo ha sido adecuado'],
        ]],
        ['bloque' => '2. Contenidos y metodología', 'preguntas' => [
            ['col' => 'item_03', 'codigo' => '2.1', 'tipo' => 'escala', 'texto' => 'Los contenidos han respondido a mis necesidades formativas'],
            ['col' => 'item_20', 'codigo' => '2.2', 'tipo' => 'escala', 'texto' => 'Combinación adecuada de teoría y aplicación práctica'],
        ]],
        ['bloque' => '3. Duración y horario', 'preguntas' => [
            ['col' => 'item_04', 'codigo' => '3.1', 'tipo' => 'escala', 'texto' => 'La duración ha sido suficiente'],
            ['col' => 'item_05', 'codigo' => '3.2', 'tipo' => 'escala', 'texto' => 'El horario ha favorecido la asistencia'],
        ]],
        ['bloque' => '4. Formadores', 'preguntas' => [
            ['col' => 'item_06', 'codigo' => '4.1', 'tipo' => 'escala', 'texto' => 'La forma de impartir ha facilitado el aprendizaje (formadores)'],
            ['col' => 'item_07', 'codigo' => '4.2', 'tipo' => 'escala', 'texto' => 'Conocen los temas en profundidad (formadores)'],
        ]],
        ['bloque' => '4. Tutores', 'preguntas' => [
            ['col' => 'item_21', 'codigo' => '4.1', 'tipo' => 'escala', 'texto' => 'La forma de tutorizar ha facilitado el aprendizaje (tutores)'],
            ['col' => 'item_22', 'codigo' => '4.2', 'tipo' => 'escala', 'texto' => 'Conocen los temas en profundidad (tutores)'],
        ]],
        ['bloque' => '5. Medios didácticos', 'preguntas' => [
            ['col' => 'item_08', 'codigo' => '5.1', 'tipo' => 'escala', 'texto' => 'Documentación y materiales comprensibles y adecuados'],
            ['col' => 'item_09', 'codigo' => '5.2', 'tipo' => 'escala', 'texto' => 'Los medios didácticos están actualizados'],
        ]],
        ['bloque' => '6. Instalaciones y medios técnicos', 'preguntas' => [
            ['col' => 'item_10', 'codigo' => '6.1', 'tipo' => 'escala', 'texto' => 'Aula / instalaciones apropiadas'],
            ['col' => 'item_11', 'codigo' => '6.2', 'tipo' => 'escala', 'texto' => 'Medios técnicos adecuados'],
        ]],
        ['bloque' => '7. Teleformación', 'preguntas' => [
            ['col' => 'item_12', 'codigo' => '7.1', 'tipo' => 'escala', 'texto' => 'Guías tutoriales y materiales han permitido realizar el curso fácilmente'],
            ['col' => 'item_13', 'codigo' => '7.2', 'tipo' => 'escala', 'texto' => 'Medios de apoyo suficientes (tutorías, correo, foros…)'],
        ]],
        ['bloque' => '8. Evaluación del aprendizaje', 'preguntas' => [
            ['col' => 'sino_pruebas_evaluacion', 'codigo' => '8.1', 'tipo' => 'sino', 'texto' => 'Ha dispuesto de pruebas de evaluación y autoevaluación'],
            ['col' => 'sino_acreditacion',       'codigo' => '8.2', 'tipo' => 'sino', 'texto' => 'El curso permite obtener una acreditación'],
        ]],
        ['bloque' => '9. Valoración general', 'preguntas' => [
            ['col' => 'item_14', 'codigo' => '9.1', 'tipo' => 'escala', 'texto' => 'Puede contribuir a mi incorporación al mercado de trabajo'],
            ['col' => 'item_15', 'codigo' => '9.2', 'tipo' => 'escala', 'texto' => 'Nuevas habilidades aplicables al puesto'],
            ['col' => 'item_16', 'codigo' => '9.3', 'tipo' => 'escala', 'texto' => 'Mejora mis posibilidades de cambiar de puesto'],
            ['col' => 'item_17', 'codigo' => '9.4', 'tipo' => 'escala', 'texto' => 'He ampliado conocimientos para progresar'],
            ['col' => 'item_18', 'codigo' => '9.5', 'tipo' => 'escala', 'texto' => 'Ha favorecido mi desarrollo personal'],
        ]],
        ['bloque' => '10. Satisfacción general', 'preguntas' => [
            ['col' => 'satisfaccion_general', 'codigo' => '10',   'tipo' => 'escala', 'texto' => 'Grado de satisfacción general con el curso'],
            ['col' => 'sino_recomendaria',    'codigo' => '10.1', 'tipo' => 'sino',   'texto' => '¿Recomendaría este curso?'],
        ]],
    ],

    /*
    |--------------------------------------------------------------------------
    | Oportunidades: ofrecer el curso siguiente a quien valoró bien el suyo
    |--------------------------------------------------------------------------
    */
    // Módulo económico FUNDAE de teleformación (€/hora): estima lo que una acción
    // formativa consumiría del crédito cuando no hay precio en el catálogo web.
    'modulo_teleformacion_hora' => (float) env('ENCUESTA_CALIDAD_MODULO_HORA', 7),
    'oportunidad_nota_minima' => (int) env('ENCUESTA_CALIDAD_OPORTUNIDAD_NOTA', 4),
    'oportunidad_estados' => [
        'pendiente'     => 'Pendiente',
        'contactado'    => 'Contactado',
        'interesado'    => 'Interesado',
        'no_interesado' => 'No interesado',
        'matriculado'   => 'Matriculado',
    ],
];
