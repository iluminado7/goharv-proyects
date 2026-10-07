<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request): View
    {
        $filters = $request->only(['action', 'user', 'tipo']);

        return view('activity.index', [
            'activities' => Activity::with(['user', 'project'])
                ->filtered($filters)
                ->latest('id')
                ->paginate(self::PER_PAGE)
                ->withQueryString(),
            'filters' => $filters,
            'acciones' => ActivityAction::cases(),
            'members' => User::orderBy('name')->get(),
            // Los fallos de ingreso de la ultima semana: 
            // es el numero que uno viene a mirar cuando entra aca.
            'fallosSemana' => Activity::whereIn('action', [
                ActivityAction::ClaveIncorrecta->value,
                ActivityAction::CorreoInexistente->value,
                ActivityAction::CuentaDeBaja->value,
            ])->where('created_at', '>=', now()->subWeek())->count(),
        ]);
    }
}
