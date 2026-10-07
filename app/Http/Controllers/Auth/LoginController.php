<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.strtolower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Esperá '.RateLimiter::availableIn($key).' segundos.',
            ]);
        }

        if (! Auth::attempt($data, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);

            // En la bitácora sí se distingue entre un correo que no existe y
            // una clave equivocada: al que lee el log le dicen cosas distintas
            // —alguien tanteando correos, o alguien que olvidó su clave—. A
            // quien intenta entrar se le responde lo mismo en los dos casos.
            $existente = User::firstWhere('email', $data['email']);

            Activity::anotar(
                $existente ? ActivityAction::ClaveIncorrecta : ActivityAction::CorreoInexistente,
                user: $existente,
                subject: $data['email'],
            );

            throw ValidationException::withMessages([
                'email' => 'Esos datos no coinciden con ninguna cuenta.',
            ]);
        }

        if (! Auth::user()->is_active) {
            Activity::anotar(ActivityAction::CuentaDeBaja, user: Auth::user(), subject: $data['email']);

            Auth::logout();
            RateLimiter::hit($key, 60);

            // Mismo texto que para una clave equivocada, a proposito: si dijera
            // "cuenta dada de baja" estaria confirmando que el correo existe y
            // que la clave probada era la correcta.
            throw ValidationException::withMessages([
                'email' => 'Esos datos no coinciden con ninguna cuenta.',
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        Activity::anotar(ActivityAction::Ingreso, user: $request->user());

        return redirect()->intended(route('projects.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Activity::anotar(ActivityAction::Salida, user: $request->user());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
