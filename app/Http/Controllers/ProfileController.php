<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user'     => $request->user(),
            'projects' => $request->user()->ownedProjects()->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($data);

        return redirect()
            ->route('profile.edit')
            ->with('ok', 'Datos actualizados.');
    }

    /** Pide la clave actual: sin eso, una sesion abierta ajena cambia la clave. */
    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::defaults()],
        ], [
            'current_password.current_password' => 'La clave actual no coincide.',
            'password.confirmed'                => 'La repetición no coincide con la clave nueva.',
        ]);

        $request->user()->update(['password' => $data['password']]);
        $request->session()->regenerate();

        return redirect()
            ->route('profile.edit')
            ->with('ok', 'Clave actualizada.');
    }
}
