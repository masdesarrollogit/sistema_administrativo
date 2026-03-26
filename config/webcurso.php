<?php

return [
    'vistas' => [
        'empresas' => [
            'modulo_candidato' => false,
            'modulo_saldo' => true,
            'columnas_tabla' => [
                'id' => 'ID',
                'cif' => 'CIF',
                'razon_social' => 'Razón Social',
                'credito_disponible' => 'Disponible',
            ],
            'campos_modal' => [
                'cif' => 'NIF',
                'importe_reserva_2023' => 'Importe Reserva 2024',
                'importe_reserva_2024' => 'Importe Reserva 2025',
                'plantilla_media' => 'Empleados',
                'credito_asignado' => 'Crédito Asignado',
                'cofinanciacion_privada_exigido' => 'Cofinanciación Privada Exigido',
                'nueva_creacion' => 'Nueva Creación',
                'pyme' => 'Pyme',
                'bloqueada' => 'Bloqueada',
                'telefono' => 'Teléfono',
                'email' => 'Email',
            ],
        ],
        'empresas_sin_grupos' => [
            'modulo_candidato' => true,
            'modulo_saldo' => true,
            'columnas_tabla' => [
                'id' => 'ID',
                'cif' => 'CIF',
                'razon_social' => 'Razón Social',
                'credito_disponible' => 'Disponible',
            ],
            'campos_modal' => [
                'expediente' => 'Expediente',
                'plantilla_media' => 'Empleados',
                'credito_asignado' => 'Crédito Asignado',
                'credito_dispuesto' => 'Crédito Dispuesto',
                'pyme' => 'Pyme',
                'bloqueada' => 'Bloqueada',
                'poblacion' => 'Población',
                'telefono' => 'Teléfono',
                'email' => 'Email',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Datos del centro de formación (para XML FUNDAE)
    |--------------------------------------------------------------------------
    */
    'centro' => [
        'cif' => env('WEBCURSO_CENTRO_CIF', 'B65828857'),
        'nombre' => env('WEBCURSO_CENTRO_NOMBRE', 'MARKETING SOFTWARE 2012'),
        'direccion' => env('WEBCURSO_CENTRO_DIRECCION', 'CALLE ARIBAU 161, BARCELONA'),
        'cod_postal' => env('WEBCURSO_CENTRO_CP', '08036'),
        'localidad' => env('WEBCURSO_CENTRO_LOCALIDAD', 'BARCELONA'),
        'telefono' => env('WEBCURSO_CENTRO_TELEFONO', '601233530'),
        'responsable' => env('WEBCURSO_CENTRO_RESPONSABLE', 'Alvaro Pino'),
    ],
];
