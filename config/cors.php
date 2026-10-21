<?php

/*
| CORS: solo para la API que reciben los formularios del sitio (goharv.com.ar).
| El panel en si no se llama desde otro dominio, asi que no entra aca.
|
| Los origenes salen de CORS_ALLOWED_ORIGINS (separados por coma), para no
| tocar codigo al pasar de localhost al dominio propio. Sin la variable no se
| acepta ningun origen: mejor que el formulario falle a que cualquier pagina
| pueda escribir en la base desde el navegador de un visitante.
*/

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['POST'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Accept'],

    'exposed_headers' => [],

    // El navegador recuerda el permiso un dia y no repite la consulta previa.
    'max_age' => 86400,

    'supports_credentials' => false,

];
