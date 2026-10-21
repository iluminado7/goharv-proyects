<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsultingRequest;
use App\Support\WebForms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Recibe "Quiero analizar mi empresa" de /consulting (scripts/consulting-form.ts).
 *
 * Llega de dos formas:
 * - Con JavaScript: JSON por fetch. Se contesta JSON, como los demas formularios.
 * - Sin JavaScript: envio comun del <form> (application/x-www-form-urlencoded,
 *   `consent=on`, sin `newsletter` si no se marco). El navegador queda en la
 *   respuesta, asi que se lo devuelve a la pagina del sitio de la que vino.
 */
class ConsultingRequestController extends Controller
{
    /**
     * Donde se guarda. "Quiero fortalecer mi empresa" (/compliance) usa el mismo
     * formulario y cambia solo esto (ComplianceRequestController).
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected string $model = ConsultingRequest::class;

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        // Campo trampa: se contesta como si todo saliera bien, sin guardar.
        if ($request->filled('website')) {
            return $this->done($request);
        }

        $data = $request->validate([
            // Clave de la opcion elegida ("costos", "ia-automatizacion").
            'challenge' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
            'goal'      => ['required', 'string', 'max:500'],
            'name'      => ['required', 'string', 'max:120'],
            'role'      => ['required', 'string', 'max:120'],
            'company'   => ['required', 'string', 'max:120'],
            'email'     => ['required', 'string', 'email:rfc', 'max:255'],
            'phone'     => ['required', 'string', 'regex:/^\d{8,15}$/'],
            // `accepted` acepta tanto true (JSON) como "on" (envio sin JS).
            'consent'   => ['accepted'],
            'page'      => ['nullable', 'string', 'max:255'],
            // Un idioma desconocido no rechaza la consulta: se guarda sin idioma.
            'locale'    => ['nullable', 'string', 'max:5'],
        ]);

        $this->model::create([
            ...collect($data)->except('consent')->all(),
            // Sin validar: "on" (envio sin JS) no pasa la regla `boolean`.
            'newsletter'   => $request->boolean('newsletter'),
            'locale'       => array_key_exists($data['locale'] ?? '', WebForms::LOCALES) ? $data['locale'] : null,
            'consented_at' => now(),
            'ip'           => $request->ip(),
        ]);

        return $this->done($request);
    }

    /**
     * JSON para el fetch. Para el envio sin JS, vuelta a la pagina de origen
     * (solo si es uno de los dominios del sitio, los mismos de CORS: el panel
     * no redirige a cualquier lado), con `?consulta=enviada` al bloque Hablemos.
     */
    private function done(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->isJson()) {
            return response()->json(['ok' => true], 201);
        }

        $from = (string) $request->headers->get('referer', '');
        $origin = parse_url($from, PHP_URL_SCHEME).'://'.parse_url($from, PHP_URL_HOST)
            .(parse_url($from, PHP_URL_PORT) ? ':'.parse_url($from, PHP_URL_PORT) : '');

        if (! in_array($origin, config('cors.allowed_origins', []), true)) {
            return response()->json(['ok' => true], 201);
        }

        $path = parse_url($from, PHP_URL_PATH) ?: '/';

        return redirect()->away($origin.$path.'?consulta=enviada#hablemos', 303);
    }
}
