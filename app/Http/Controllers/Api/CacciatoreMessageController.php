<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Mail\CacciatoreMessageReceived;
use App\Models\Activity;
use App\Models\CacciatoreMessage;
use App\Models\User;
use App\Notifications\ConsultaRecibida;
use App\Support\Avisos;
use App\Support\Turnstile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Recibe el formulario de contacto de cacciatoreabogados.com.ar (sitio
 * estatico en S3 + CloudFront, sin backend propio).
 *
 * Primero guarda y despues avisa: bitacora, campana del equipo y mail al
 * estudio. Ninguno de los avisos puede voltear el envio: la consulta ya esta
 * en Consultas web, y del otro lado alguien espera el "¡Gracias!".
 *
 * Las defensas, de la mas barata a la mas cara: limite por IP (routes/api.php),
 * campo trampa, validacion y Cloudflare Turnstile. Este formulario manda mail,
 * y un mail por cada envio de un bot es justo lo que hay que evitar.
 */
class CacciatoreMessageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Campo trampa: invisible para las personas, los bots lo completan.
        // Se contesta como si todo saliera bien, asi el bot no aprende nada.
        if ($request->filled('website')) {
            return response()->json(['ok' => true], 201);
        }

        // El sitio pide el telefono "sin espacios", pero hay quien los pone
        // igual, o guiones y parentesis. Se sacan antes de validar en vez de
        // rechazar a alguien por escribir su numero como se escribe.
        if (is_string($request->input('phone'))) {
            $request->merge(['phone' => preg_replace('/[\s\-().]/', '', $request->input('phone'))]);
        }

        // Una sola linea: varios de estos campos terminan en el asunto o en la
        // cabecera Reply-To del mail, y un salto de linea ahi es la forma de
        // colar cabeceras propias.
        $unaLinea = 'not_regex:/[\r\n]/';

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:120', $unaLinea],
            'last_name' => ['required', 'string', 'max:120', $unaLinea],
            'company'   => ['required', 'string', 'max:120', $unaLinea],
            'email'     => ['required', 'string', 'email:rfc', 'max:255', $unaLinea],
            // Con codigo de pais opcional: el estudio atiende clientes de afuera.
            'phone'     => ['required', 'string', 'regex:/^\+?\d{8,15}$/'],
            'country'   => ['required', 'string', 'max:60', $unaLinea],
            'message'   => ['required', 'string', 'max:500'],
            // El sitio tiene dos casillas: aviso legal y politica de privacidad.
            'legal'     => ['accepted'],
            'privacy'   => ['accepted'],
            'page'      => ['nullable', 'string', 'max:255'],
            // Un idioma desconocido no rechaza la consulta: se guarda sin idioma.
            'locale'    => ['nullable', 'string', 'max:5'],
            'turnstile' => ['required', 'string', 'max:2048'],
        ]);

        // Despues de validar: no tiene sentido consultar a Cloudflare por un
        // envio que igual se iba a rechazar.
        if (! Turnstile::valido($data['turnstile'], $request->ip())) {
            throw ValidationException::withMessages([
                'turnstile' => 'No pudimos verificar que no seas un robot. Probá de nuevo.',
            ]);
        }

        $consulta = CacciatoreMessage::create([
            ...collect($data)->except(['legal', 'privacy', 'turnstile'])->all(),
            'locale'       => array_key_exists($data['locale'] ?? '', CacciatoreMessage::LOCALES) ? $data['locale'] : null,
            'consented_at' => now(),
            'ip'           => $request->ip(),
        ]);

        Activity::anotar(ActivityAction::ConsultaRecibida, subject: $consulta->deQuien(), detail: $consulta->company);

        // Al equipo interno: el profesional externo solo ve lo que le delegaron.
        Avisos::mandar(
            User::where('is_active', true)->get()->filter(fn (User $u) => $u->isInternal()),
            new ConsultaRecibida($consulta),
        );

        $this->avisarAlEstudio($consulta);

        return response()->json(['ok' => true], 201);
    }

    /**
     * Mail al estudio con la consulta completa. Sale en el mismo request
     * porque el panel no tiene worker de colas.
     */
    private function avisarAlEstudio(CacciatoreMessage $consulta): void
    {
        $destinos = collect(explode(',', (string) config('services.cacciatore.mail_to')))
            ->map(fn ($d) => trim($d))
            ->filter()
            ->values();

        // Con MAIL_MAILER=log Laravel escribiria el mail entero en el log,
        // datos personales incluidos: sin correo de verdad, no se manda.
        if ($destinos->isEmpty() || ! Avisos::hayCorreo()) {
            return;
        }

        try {
            Mail::to($destinos->all())->send(new CacciatoreMessageReceived($consulta));
        } catch (\Throwable $e) {
            Log::error('No se pudo mandar el mail de una consulta de Cacciatore.', [
                'consulta' => $consulta->getKey(),
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
