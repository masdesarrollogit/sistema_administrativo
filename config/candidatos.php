<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuración del Cron de Recordatorios
    |--------------------------------------------------------------------------
    |
    | Parámetros para el comportamiento del cron que envía recordatorios
    | a candidatos con requisitos pendientes.
    |
    */
    
    'recordatorios' => [
        // Días de espera entre recordatorios
        'dias_entre_envios' => env('CANDIDATOS_DIAS_ENTRE_RECORDATORIOS', 3),
        
        // Máximo de recordatorios antes de pausar
        'max_recordatorios' => env('CANDIDATOS_MAX_RECORDATORIOS', 5),
        
        // Email para recibir copia de cada recordatorio enviado (BCC)
        'copia_email' => env('CANDIDATOS_COPIA_EMAIL'),
        
        // Hora de ejecución para recordatorios individuales (9:00 por defecto)
        'recordatorios_hora' => env('CANDIDATOS_HORA_RECORDATORIOS', '09:00'),

        // Hora de ejecución para el resumen administrativo (13:00 por defecto)
        'resumen_hora' => env('CANDIDATOS_HORA_RESUMEN', '13:00'),
        
        // Email para notificar errores
        'email_errores' => env('CANDIDATOS_EMAIL_ERRORES'),
        
        // Activar/desactivar envío de recordatorios
        'activo' => env('CANDIDATOS_RECORDATORIOS_ACTIVOS', true),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Configuración de Empresas
    |--------------------------------------------------------------------------
    */
    
    'empresas' => [
        // Crear empresa automáticamente si no existe (Tipo 1)
        'auto_crear' => true,
        
        // Crear empresa externa automáticamente si no existe (Tipo 2)
        'auto_crear_externa' => true,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Configuración de Notificaciones
    |--------------------------------------------------------------------------
    */
    
    'notificaciones' => [
        // Remitente de los emails
        'from_email' => env('MAIL_FROM_ADDRESS'),
        'from_name' => env('MAIL_FROM_NAME'),
        
        // Destinatarios del email de saldo (separados por coma en .env)
        'destinatarios_saldo' => array_filter(array_map('trim', explode(',', env('CANDIDATOS_DESTINATARIOS_SALDO', '')))),
        
        // Asunto del email de recordatorio
        'asunto_recordatorio' => 'Recordatorio: Requisitos pendientes para tu curso',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Datos de Contacto (firma de emails)
    |--------------------------------------------------------------------------
    */
    
    'contacto' => [
        'email' => env('CONTACTO_EMAIL'),
        'telefono' => env('CONTACTO_TELEFONO'),
        'whatsapp' => env('CONTACTO_WHATSAPP'),
        'whatsapp_display' => env('CONTACTO_WHATSAPP_DISPLAY'),
    ],
];
