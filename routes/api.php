<?php

use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\LeadController;
use Illuminate\Support\Facades\Route;

/*
| Formularios del sitio goharv.com.ar. Llegan como POST con JSON desde otro
| dominio (ver config/cors.php) y sin sesion: no pasan por el grupo web, asi
| que no llevan CSRF, ni la CSP, ni el chequeo de cuentas de baja.
|
| El limite es por IP. Una persona real manda uno; cinco por minuto ya es un
| bot o alguien apretando el boton sin parar.
*/

Route::middleware('throttle:5,1')->group(function () {
    Route::post('/contacto', [ContactMessageController::class, 'store'])->name('api.contact.store');
    Route::post('/avisame', [LeadController::class, 'store'])->name('api.leads.store');
});
