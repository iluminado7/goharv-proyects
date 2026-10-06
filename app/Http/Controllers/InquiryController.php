<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\Lead;
use App\Support\WebForms;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consultas web: lo que llega de los formularios del sitio. Se ve de a un
 * formulario por vez (Contacto, o el "Avisame" de cada programa) y dentro de
 * cada uno se filtra por fecha y, en Contacto, por empresa y por unidad.
 */
class InquiryController extends Controller
{
    /** Consultas por pagina, igual que el tablero. */
    private const PER_PAGE = 30;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'form'    => ['nullable', 'string', 'max:80'],
            'desde'   => ['nullable', 'date_format:Y-m-d'],
            'hasta'   => ['nullable', 'date_format:Y-m-d'],
            'empresa' => ['nullable', 'string', 'max:120'],
            'unidad'  => ['nullable', 'string', 'max:60'],
        ]);

        $form  = $filters['form'] ?? WebForms::CONTACT;
        $desde = $filters['desde'] ?? null;
        $hasta = $filters['hasta'] ?? null;

        // Los contadores respetan las fechas: asi se ve cuantos llegaron de
        // cada formulario en ese periodo antes de entrar a uno.
        $leadCounts = Lead::receivedBetween($desde, $hasta)
            ->selectRaw('program, count(*) as total')
            ->groupBy('program')
            ->pluck('total', 'program');

        // Los programas conocidos aparecen aunque todavia no tengan pedidos;
        // uno nuevo que mande el sitio aparece apenas llega el primero.
        $programs = collect(array_keys(WebForms::PROGRAMS))
            ->merge(Lead::distinct()->pluck('program'))
            ->unique()
            ->values();

        abort_unless($form === WebForms::CONTACT || $programs->contains($form), 404);

        $contacto = $form === WebForms::CONTACT;
        // Empresa y unidad solo existen en Contacto: "Avisame" no las pide.
        $empresa  = $contacto ? ($filters['empresa'] ?? null) : null;
        $unidad   = $contacto ? ($filters['unidad'] ?? null) : null;

        $items = $contacto
            ? ContactMessage::receivedBetween($desde, $hasta)
                ->when($empresa, fn ($q, $e) => $q->where('company', $e))
                ->when($unidad, fn ($q, $u) => $q->where('unit', $u))
            : Lead::receivedBetween($desde, $hasta)->where('program', $form);

        return view('inquiries.index', [
            'form'         => $form,
            'contacto'     => $contacto,
            'items'        => $items->latest()->latest('id')->paginate(self::PER_PAGE)->withQueryString(),
            'contactCount' => ContactMessage::receivedBetween($desde, $hasta)->count(),
            'leadCounts'   => $leadCounts,
            'programs'     => $programs,
            'empresas'     => $contacto
                ? ContactMessage::whereNotNull('company')->distinct()->orderBy('company')->pluck('company')
                : collect(),
            // Las que llegaron, no una lista fija: si el sitio suma una unidad
            // (o la opcion "No estoy seguro"), aparece sola en el filtro.
            'unidades'     => $contacto
                ? ContactMessage::whereNotNull('unit')->distinct()->orderBy('unit')->pluck('unit')
                : collect(),
            'filters'      => ['desde' => $desde, 'hasta' => $hasta, 'empresa' => $empresa, 'unidad' => $unidad],
        ]);
    }
}
