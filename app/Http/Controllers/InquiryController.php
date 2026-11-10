<?php

namespace App\Http\Controllers;

use App\Models\CacciatoreMessage;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Consultas web: lo que llega del formulario de contacto de
 * cacciatoreabogados.com.ar. Solo lectura: las consultas se guardan por la
 * API (routes/api.php) y se contestan por mail.
 */
class InquiryController extends Controller
{
    /** Consultas por pagina, igual que el tablero. */
    private const PER_PAGE = 30;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'desde'   => ['nullable', 'date_format:Y-m-d'],
            'hasta'   => ['nullable', 'date_format:Y-m-d'],
            'empresa' => ['nullable', 'string', 'max:120'],
        ]);

        $filters += ['desde' => null, 'hasta' => null, 'empresa' => null];

        $items = CacciatoreMessage::receivedBetween($filters['desde'], $filters['hasta'])
            ->when($filters['empresa'], fn ($q, $e) => $q->where('company', $e))
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('inquiries.index', [
            'items'    => $items,
            'filters'  => $filters,
            // Las empresas que llegaron, no una lista fija.
            'empresas' => CacciatoreMessage::distinct()->orderBy('company')->pluck('company'),
        ]);
    }
}
