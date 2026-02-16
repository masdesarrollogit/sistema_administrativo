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
];
