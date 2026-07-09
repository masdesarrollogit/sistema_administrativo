<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sistema externo de Encomienda (contrato-encomienda.php)
    |--------------------------------------------------------------------------
    |
    | Conexión a la BD del sistema externo de firma de contratos y descarga
    | del PDF firmado vía endpoint con token. La conexión MySQL se registra
    | on-the-fly en el comando `encomienda:sincronizar` (patrón de
    | ImportLegacyData). En dev apunta al contenedor local con el snapshot.
    |
    */

    'db' => [
        'host'     => env('ENCOMIENDA_DB_HOST'),
        'port'     => env('ENCOMIENDA_DB_PORT', '3306'),
        'database' => env('ENCOMIENDA_DB_DATABASE', 'webcourses2014'),
        'username' => env('ENCOMIENDA_DB_USERNAME'),
        'password' => env('ENCOMIENDA_DB_PASSWORD'),
    ],

    // Nombres de las tablas en el sistema externo.
    'tablas' => [
        'contratos' => 'contratos_encomienda',
        'alumnos'   => 'encomienda_alumnos',
    ],

    // Descarga del PDF firmado. base_url es pública; token es secreto (.env).
    // Si ambas están vacías, la descarga automática se desactiva (subida manual).
    'pdf' => [
        'base_url' => env('ENCOMIENDA_PDF_BASE_URL'),
        'token'    => env('ENCOMIENDA_PDF_TOKEN'),
        'timeout'  => (int) env('ENCOMIENDA_PDF_TIMEOUT', 20),
    ],
];
