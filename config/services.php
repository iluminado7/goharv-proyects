<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    /*
     * Cloudflare Turnstile: el "no soy un robot" del formulario de
     * cacciatoreabogados.com.ar. La clave del sitio es publica y va en el
     * HTML; esta es la secreta y vive solo aca. Sin ella, la verificacion
     * falla y no se guarda nada: mejor eso a que el formulario quede abierto a
     * los bots sin que nadie lo note.
     */
    'turnstile' => [
        'secret' => env('TURNSTILE_SECRET_KEY'),
    ],

    /*
     * A donde avisa por mail el contacto de cacciatoreabogados.com.ar.
     * `mail_to` acepta varias direcciones separadas por coma. `mail_from` tiene
     * que ser de un dominio verificado en Resend; vacio, usa MAIL_FROM_ADDRESS.
     */
    'cacciatore' => [
        'mail_to'   => env('CACCIATORE_MAIL_TO'),
        'mail_from' => env('CACCIATORE_MAIL_FROM'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
