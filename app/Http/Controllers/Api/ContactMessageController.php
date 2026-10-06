<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recibe el formulario de contacto del sitio. Las reglas son las mismas que
 * valida el sitio en el navegador (scripts/contact-form.ts): se repiten aca
 * porque esa validacion la saltea cualquiera que mande el POST a mano.
 */
class ContactMessageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Campo trampa: invisible para las personas, los bots lo completan.
        // Se contesta como si todo saliera bien, asi el bot no aprende nada.
        if ($request->filled('website')) {
            return response()->json(['ok' => true], 201);
        }

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:120'],
            'email'   => ['required', 'string', 'email:rfc', 'max:255'],
            'phone'   => ['required', 'string', 'regex:/^\d{8,15}$/'],
            'company' => ['nullable', 'string', 'max:120'],
            // Texto libre: es lo que eligio la persona en la lista del sitio.
            'country' => ['nullable', 'string', 'max:60'],
            'unit'    => ['nullable', 'string', 'max:60'],
            'message' => ['required', 'string', 'max:500'],
            'consent' => ['accepted'],
            'page'    => ['nullable', 'string', 'max:255'],
        ]);

        ContactMessage::create([
            ...collect($data)->except('consent')->all(),
            'consented_at' => now(),
            'ip'           => $request->ip(),
        ]);

        return response()->json(['ok' => true], 201);
    }
}
