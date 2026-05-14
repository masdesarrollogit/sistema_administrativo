<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Reporte: Alumnos no conectados
    |--------------------------------------------------------------------------
    | Detecta alumnos que NO han accedido a su curso. Engloba dos sub-estados
    | que se distinguen visualmente con badge propio:
    |   · Nunca al sitio (amarillo)  → lastaccess_global = 0
    |   · No entró al curso (naranja) → lastaccess_global > 0 pero lastaccess_curso = 0
    */
    'reporte_no_conectados' => [
        'tope_reenvios_alumno'     => env('REPORTES_MOODLE_TOPE_REENVIOS', 3),
        'dias_disparo_alumno'      => [3, 6, 9],
        'cron_alumno_hora'         => '10:00',
        'cron_tutor_hora_lunes'    => '09:00',
        'incluir_tutores_externos' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporte: Alumnos inactivos
    |--------------------------------------------------------------------------
    | Alumnos que entraron al curso alguna vez pero llevan más de N días sin
    | volver, AND no han aprobado (nota_total < 50), AND no han realizado el
    | cuestionario final. Se envía email de rescate cada `throttle_horas`.
    */
    'reporte_inactivos' => [
        'umbral_inactividad_dias'   => env('REPORTES_MOODLE_UMBRAL_INACTIVIDAD', 3),
        'umbral_aprobado_puntos'    => env('REPORTES_MOODLE_UMBRAL_APROBADO', 50),
        'throttle_horas'            => env('REPORTES_MOODLE_INACTIVO_THROTTLE_HORAS', 72),
        'cron_alumno_hora'          => '10:15',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporte: Riesgo crítico (Reporte 3)
    |--------------------------------------------------------------------------
    | Alumnos cuya nota_total < umbral_aprobado_puntos (50) AND el % de tiempo
    | del curso transcurrido >= umbral_tiempo_pct (50). Reciben email de rescate
    | semanal (throttle 168h = 7 días) hasta que aprueben o termine el curso.
    */
    'reporte_riesgo_critico' => [
        'umbral_tiempo_pct'  => env('REPORTES_MOODLE_RIESGO_UMBRAL_TIEMPO_PCT', 50),
        'throttle_horas'     => env('REPORTES_MOODLE_RIESGO_THROTTLE_HORAS', 168), // 7 días
        'cron_alumno_hora'   => '10:30',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporte: Pre-cierre (Reporte 4)
    |--------------------------------------------------------------------------
    | Últimas N horas (72 por defecto) del curso AND cuestionario_final no
    | realizado. Email diario sin tope durante esa ventana. Tiene PRIORIDAD
    | sobre Riesgo crítico (R3): si un alumno cumple R4, no recibe email R3.
    */
    'reporte_pre_cierre' => [
        'umbral_horas'     => env('REPORTES_MOODLE_PRE_CIERRE_UMBRAL_HORAS', 72),
        'throttle_horas'   => env('REPORTES_MOODLE_PRE_CIERRE_THROTTLE_HORAS', 23), // diario sin doble envío
        'cron_alumno_hora' => '10:45',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporte: Apto sin examen (Reporte 5)
    |--------------------------------------------------------------------------
    | Alumno con nota_total >= 50 pts AND cuestionario_final no realizado.
    | Email semanal con tono positivo: "ya casi llegas, solo falta cerrar
    | con el cuestionario final". R4 tiene prioridad si está en últimas 72h.
    */
    'reporte_apto_sin_examen' => [
        'throttle_horas'    => env('REPORTES_MOODLE_APTO_SIN_EXAMEN_THROTTLE_HORAS', 168), // 7 días
        'cron_alumno_hora'  => '11:00',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporte: Apto / Finalizado con éxito (Reporte 6)
    |--------------------------------------------------------------------------
    | aprobado = true (nota >= 50 AND cuestionario_final_realizado).
    | Email de FELICITACIÓN al alumno una SOLA vez al detectar la aprobación.
    | No es alerta, es confirmación de cierre.
    */
    'reporte_apto' => [
        'cron_alumno_hora'  => '11:15',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporte: No aptos (Reporte 7)
    |--------------------------------------------------------------------------
    | Alumnos cuyo curso ya finalizó (fecha_fin < hoy) AND nota_total < 50
    | AND cuestionario_final_realizado = false → suspendieron.
    | Se les ofrece REINICIAR el curso vía email semanal hasta 4 veces en 30 días.
    | El botón del email es un mailto: al admin (sin endpoint público).
    */
    'reporte_no_aptos' => [
        'ventana_oferta_dias'   => env('REPORTES_MOODLE_NO_APTOS_VENTANA_DIAS', 30),
        'intervalo_dias'        => env('REPORTES_MOODLE_NO_APTOS_INTERVALO_DIAS', 7),
        'max_ofrecimientos'     => env('REPORTES_MOODLE_NO_APTOS_MAX_OFRECIMIENTOS', 4),
        'max_reinicios_alumno'  => env('REPORTES_MOODLE_NO_APTOS_MAX_REINICIOS', 0), // 0 = sin tope
        'cron_detectar_hora'    => '01:30', // antes del snapshot diario (02:00)
        'cron_notificar_hora'   => '11:30',
    ],

    /*
    |--------------------------------------------------------------------------
    | Snapshot diario
    |--------------------------------------------------------------------------
    */
    'snapshot' => [
        'cron_hora'        => '02:00',
        'reintentos'       => 2,
        'lote_lastaccess'  => 50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Exclusiones en Moodle
    |--------------------------------------------------------------------------
    | Cursos cuya categoría Moodle esté en `categorias_excluidas` no entran
    | en ningún Reporte. Los nombres se resuelven dinámicamente a IDs
    | mediante `core_course_get_categories`.
    */
    'moodle' => [
        // Nombres EXACTOS (case-insensitive) de categorías Moodle a excluir.
        // Sus IDs se resuelven dinámicamente vía `core_course_get_categories`.
        'categorias_excluidas' => ['Repaso', 'Foros', 'Foros Dominio de lo Aprendido'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Email administrativo en copia
    |--------------------------------------------------------------------------
    */
    'copia_admin_email' => env('REPORTES_MOODLE_COPIA_ADMIN', 'administracion@webcurso.es'),
];
