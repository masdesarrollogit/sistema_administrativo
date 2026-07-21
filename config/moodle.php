<?php

return [
    'url'           => env('MOODLE_URL', 'https://tu-moodle.com'),
    'token'         => env('MOODLE_TOKEN', ''),
    // URL pública de Moodle (la que ven los alumnos en el navegador)
    'public_url'    => env('MOODLE_PUBLIC_URL', 'https://aula.1curso.com'),
    // Email desde el que se envían las credenciales de acceso al alumno
    'mail_from'     => env('MOODLE_MAIL_FROM', 'info@aula.1curso.com'),
    // En desarrollo Docker, Moodle corre en otra red. MOODLE_HOST_OVERRIDE
    // permite enviar el Host header correcto (el wwwroot de Moodle) mientras
    // la conexión real se hace a la URL interna (MOODLE_URL).
    'host_override' => env('MOODLE_HOST_OVERRIDE', ''),

    // Días extra que la matrícula del tutor sigue activa después de la fecha
    // fin del grupo. Permite corregir y calificar lo que los alumnos entregaron
    // el último día: sin este margen, el tutor pierde el acceso al aula a la vez
    // que ellos y no puede evaluar esas entregas.
    'tutor_dias_extra' => (int) env('MOODLE_TUTOR_DIAS_EXTRA', 10),
];
