<?php

namespace App\Http\Controllers;

use App\Models\ConsultingRequest;
use App\Models\ContactMessage;
use App\Models\InCompanyRequest;
use App\Models\Lead;
use App\Support\WebForms;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consultas web: lo que llega de los formularios del sitio. Se ve de a un
 * formulario por vez (Contacto, Formacion In Company, Analizar mi empresa o el
 * "Avisame" de cada programa) y dentro de cada uno se filtra por fecha y,
 * segun el formulario, por empresa, unidad, cantidad de colaboradores o desafio.
 */
class InquiryController extends Controller
{
    /** Consultas por pagina, igual que el tablero. */
    private const PER_PAGE = 30;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'form'     => ['nullable', 'string', 'max:80'],
            'desde'    => ['nullable', 'date_format:Y-m-d'],
            'hasta'    => ['nullable', 'date_format:Y-m-d'],
            'empresa'  => ['nullable', 'string', 'max:120'],
            'unidad'   => ['nullable', 'string', 'max:60'],
            'tamano'   => ['nullable', 'string', 'max:40'],
            'desafio'  => ['nullable', 'string', 'max:40'],
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

        $kind = match (true) {
            $form === WebForms::CONTACT    => 'contacto',
            $form === WebForms::IN_COMPANY => 'in-company',
            $form === WebForms::CONSULTING => 'analizar-empresa',
            $programs->contains($form)     => 'avisame',
            default                        => abort(404),
        };

        // Cada filtro existe solo en el formulario que pide ese dato: si llega
        // en la URL de otro, no se aplica.
        $empresa = $kind !== 'avisame' ? ($filters['empresa'] ?? null) : null;
        $unidad  = $kind === 'contacto' ? ($filters['unidad'] ?? null) : null;
        $tamano  = $kind === 'in-company' ? ($filters['tamano'] ?? null) : null;
        $desafio = $kind === 'analizar-empresa' ? ($filters['desafio'] ?? null) : null;

        $model = match ($kind) {
            'contacto'         => ContactMessage::class,
            'in-company'       => InCompanyRequest::class,
            'analizar-empresa' => ConsultingRequest::class,
            'avisame'          => Lead::class,
        };

        $items = $model::receivedBetween($desde, $hasta)
            ->when($kind === 'avisame', fn ($q) => $q->where('program', $form))
            ->when($empresa, fn ($q, $e) => $q->where('company', $e))
            ->when($unidad, fn ($q, $u) => $q->where('unit', $u))
            ->when($tamano, fn ($q, $t) => $q->where('team_size', $t))
            ->when($desafio, fn ($q, $d) => $q->where('challenge', $d));

        // Las opciones de cada filtro son las que llegaron, no una lista fija:
        // si el sitio suma una opcion, aparece sola.
        $distinct = fn (string $column) => $model::whereNotNull($column)->distinct()->orderBy($column)->pluck($column);

        return view('inquiries.index', [
            'form'            => $form,
            'kind'            => $kind,
            'items'           => $items->latest()->latest('id')->paginate(self::PER_PAGE)->withQueryString(),
            'contactCount'    => ContactMessage::receivedBetween($desde, $hasta)->count(),
            'inCompanyCount'  => InCompanyRequest::receivedBetween($desde, $hasta)->count(),
            'consultingCount' => ConsultingRequest::receivedBetween($desde, $hasta)->count(),
            'leadCounts'      => $leadCounts,
            'programs'        => $programs,
            'empresas'        => $kind !== 'avisame' ? $distinct('company') : collect(),
            'unidades'        => $kind === 'contacto' ? $distinct('unit') : collect(),
            'tamanos'         => $kind === 'in-company' ? $distinct('team_size') : collect(),
            'desafios'        => $kind === 'analizar-empresa' ? $distinct('challenge') : collect(),
            'filters'         => [
                'desde'   => $desde,
                'hasta'   => $hasta,
                'empresa' => $empresa,
                'unidad'  => $unidad,
                'tamano'  => $tamano,
                'desafio' => $desafio,
            ],
        ]);
    }
}
