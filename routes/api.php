<?php

use App\Http\Controllers\Api\CacciatoreMessageController;
use Illuminate\Support\Facades\Route;

/*
| El formulario de contacto de cacciatoreabogados.com.ar. Llega como POST con
| JSON desde otro dominio (ver config/cors.php) y sin sesion: no pasa por el
| grupo web, asi que no lleva CSRF, ni la CSP, ni el chequeo de cuentas de baja.
|
| El limite es por IP. Una persona real manda una consulta; cinco por minuto ya
| es un bot o alguien apretando el boton sin parar.
*/

Route::middleware('throttle:5,1')->group(function () {
    Route::post('/cacciatore', [CacciatoreMessageController::class, 'store'])->name('api.cacciatore.store');
});
