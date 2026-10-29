<?php

/*
|--------------------------------------------------------------------------
| Configuración de ARCA (facturación electrónica)
|--------------------------------------------------------------------------
|
| Modo desarrollo (ahora):
|   ARCA_PRODUCTION=false, ARCA_CUIT=20409378472 (CUIT de prueba de Afip SDK)
|   y sin cert/key. Le pega a homologación con el certificado compartido.
|
| Con certificado propio (después):
|   Cargás ARCA_CUIT con el tuyo o el de GoHarv y las rutas del cert/key.
|   ARCA_PRODUCTION=true solo cuando salgas a producción.
|
*/

return [

    'production'   => env('ARCA_PRODUCTION', false),

    'cuit'         => (int) env('ARCA_CUIT', 20409378472),

    'pto_vta'      => (int) env('ARCA_PTO_VTA', 1),

    'access_token' => env('ARCA_ACCESS_TOKEN'),

    // Rutas absolutas o relativas a storage/app. Vacías = modo desarrollo.
    'cert_path'    => env('ARCA_CERT_PATH'),
    'key_path'     => env('ARCA_KEY_PATH'),

    // Factura C (monotributo)
    'cbte_tipo'    => 11,
];
