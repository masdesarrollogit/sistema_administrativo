<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OAuth credenciales
    |--------------------------------------------------------------------------
    | Client ID, secret y redirect URI se obtienen en
    | https://api-console.zoho.eu/ (Self Client / Server-based Applications).
    */

    'client_id'     => env('ZOHO_CLIENT_ID', ''),
    'client_secret' => env('ZOHO_CLIENT_SECRET', ''),
    'redirect_uri'  => env('ZOHO_REDIRECT_URI', 'http://localhost/zoho/callback'),

    /*
    |--------------------------------------------------------------------------
    | Región del data center
    |--------------------------------------------------------------------------
    | Zoho tiene data centers separados por región. WebCurso está en EU.
    | Valores aceptados: eu, com, in, com.au, jp, com.cn
    */

    'region' => env('ZOHO_REGION', 'eu'),

    /*
    |--------------------------------------------------------------------------
    | Refresh token de respaldo
    |--------------------------------------------------------------------------
    | El refresh token se persiste en la tabla zoho_tokens tras el OAuth.
    | Esta variable solo se usa como fallback / arranque inicial.
    */

    'refresh_token' => env('ZOHO_REFRESH_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Scopes solicitados
    |--------------------------------------------------------------------------
    | Solo lectura por ahora. Cuando se añada creación de facturas habrá que
    | reautorizar con ZohoBooks.invoices.CREATE y ZohoBooks.contacts.CREATE.
    */

    'scopes' => [
        'ZohoBooks.contacts.READ',
        'ZohoBooks.invoices.READ',
        'ZohoBooks.settings.READ',
    ],

    /*
    |--------------------------------------------------------------------------
    | Organization ID
    |--------------------------------------------------------------------------
    | Zoho Books exige `organization_id` en cada petición. Si se deja vacío,
    | el servicio lo auto-detecta llamando a /organizations y lo cachea en BD.
    */

    'organization_id' => env('ZOHO_BOOKS_ORGANIZATION_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Campos custom de Zoho Books
    |--------------------------------------------------------------------------
    | api_name del campo donde se guarda el CIF (en facturas y/o contactos).
    | Descubre el nombre real con `php artisan zoho:probe-contact`. En cuentas
    | españolas típicas suele ser `cf_cif`. El campo de teléfono casi siempre
    | es `phone` built-in, pero se puede sobreescribir vía env.
    */

    'contact_cif_field'   => env('ZOHO_BOOKS_CIF_FIELD', 'cf_cif'),
    'contact_phone_field' => env('ZOHO_BOOKS_PHONE_FIELD', 'phone'),

];
