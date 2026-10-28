<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * El primer paso de quien recibe una cuenta nueva, o de quien le reseteo la
 * clave un responsable: cambiar la clave que le llego por correo antes de
 * poder hacer cualquier otra cosa.
 *
 * Es una pantalla aparte y no la de Mi perfil porque hasta aca no se llega
 * navegando: te trae el middleware, y todo lo demas del panel esta cerrado.
 */
class PasswordChangeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        // Entrar a mano con la clave ya cambiada no tiene sentido.
        if (! $request->user()->must_change_password) {
            return redirect()->route('projects.index');
        }

        return view('auth.password-change');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::defaults(), 'different:current_password'],
        ], [
            'current_password.required'         => 'Escribí la clave que te llegó por correo.',
            'current_password.current_password' => 'Esa no es la clave que te mandamos.',
            'password.confirmed'                => 'La repetición no coincide con la clave nueva.',
            'password.different'                => 'Tiene que ser distinta de la que te mandamos.',
        ]);

        $request->user()->update([
            'password'             => $data['password'],
            'must_change_password' => false,
        ]);

        $request->session()->regenerate();

        return redirect()
            ->route('projects.index')
            ->with('ok', 'Listo. Bienvenido al panel.');
    }
}
