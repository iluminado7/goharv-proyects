<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recibe el formulario "Avisame" de las paginas de unidad del sitio
 * (scripts/notify-form.ts). Mismas reglas que valida el navegador.
 */
class LeadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Campo trampa: ver ContactMessageController.
        if ($request->filled('website')) {
            return response()->json(['ok' => true], 201);
        }

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:120'],
            'email'      => ['required', 'string', 'email:rfc', 'max:255'],
            'phone'      => ['required', 'string', 'regex:/^\d{8,15}$/'],
            'consent'    => ['accepted'],
            'newsletter' => ['boolean'],
            // Etiquetas internas que arma el sitio (ej. "workshop-consorcios", "academy").
            'program'    => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/'],
            'source'     => ['required', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
            'page'       => ['nullable', 'string', 'max:255'],
        ]);

        Lead::create([
            ...collect($data)->except('consent')->all(),
            'newsletter'   => $request->boolean('newsletter'),
            'consented_at' => now(),
            'ip'           => $request->ip(),
        ]);

        return response()->json(['ok' => true], 201);
    }
}
