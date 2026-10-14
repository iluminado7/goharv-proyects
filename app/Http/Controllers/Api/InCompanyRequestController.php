<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InCompanyRequest;
use App\Support\WebForms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recibe el formulario de Formacion In Company del sitio
 * (/academy/formacion-in-company, scripts/in-company-form.ts). Mismas reglas
 * que valida el navegador.
 */
class InCompanyRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Campo trampa: se contesta como si todo saliera bien, sin guardar.
        if ($request->filled('website')) {
            return response()->json(['ok' => true], 201);
        }

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:120'],
            'role'       => ['required', 'string', 'max:120'],
            'company'    => ['required', 'string', 'max:120'],
            'email'      => ['required', 'string', 'email:rfc', 'max:255'],
            'phone'      => ['required', 'string', 'regex:/^\d{8,15}$/'],
            // Texto libre: es lo que eligio la persona en las listas del sitio.
            'teamSize'   => ['required', 'string', 'max:40'],
            'areas'      => ['nullable', 'array', 'max:20'],
            'areas.*'    => ['string', 'max:120'],
            'modality'   => ['nullable', 'string', 'max:60'],
            'message'    => ['nullable', 'string', 'max:500'],
            'consent'    => ['accepted'],
            'newsletter' => ['boolean'],
            'page'       => ['nullable', 'string', 'max:255'],
            // Un idioma desconocido no rechaza la consulta: se guarda sin idioma.
            'locale'     => ['nullable', 'string', 'max:5'],
        ]);

        InCompanyRequest::create([
            'name'         => $data['name'],
            'role'         => $data['role'],
            'company'      => $data['company'],
            'email'        => $data['email'],
            'phone'        => $data['phone'],
            'team_size'    => $data['teamSize'],
            'areas'        => array_values($data['areas'] ?? []) ?: null,
            'modality'     => $data['modality'] ?? null,
            'message'      => $data['message'] ?? null,
            'newsletter'   => $request->boolean('newsletter'),
            'page'         => $data['page'] ?? null,
            'locale'       => array_key_exists($data['locale'] ?? '', WebForms::LOCALES) ? $data['locale'] : null,
            'consented_at' => now(),
            'ip'           => $request->ip(),
        ]);

        return response()->json(['ok' => true], 201);
    }
}
