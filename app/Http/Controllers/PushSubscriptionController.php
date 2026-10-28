<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Minishlink\WebPush\ContentEncoding;
use NotificationChannels\WebPush\PushSubscription;

/**
 * Alta y baja de la suscripcion de un celular a las notificaciones.
 *
 * Una suscripcion es por dispositivo, no por persona: el mismo usuario puede
 * tener el telefono y la computadora, y cada uno es una fila. Las que caducan
 * o se revocan las borra solo el paquete cuando el envio devuelve 410.
 *
 * Contesta JSON porque la llama el JavaScript del boton: no hay una pantalla
 * detras de esto.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // El endpoint lo arma el navegador y es siempre una URL https de
        // Google, Apple o Mozilla. El content_encoding es un enum: cualquier
        // otro valor hace estallar ContentEncoding::from() con un 500 en vez
        // de un error de validacion.
        $data = $request->validate([
            'endpoint'         => ['required', 'string', 'url:https', 'max:'.PushSubscription::ENDPOINT_MAX_LENGTH],
            'keys.p256dh'      => ['required', 'string', 'max:255'],
            'keys.auth'        => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', Rule::enum(ContentEncoding::class)],
        ]);

        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['content_encoding'] ?? null,
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:'.PushSubscription::ENDPOINT_MAX_LENGTH],
        ]);

        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->json(['ok' => true]);
    }
}
