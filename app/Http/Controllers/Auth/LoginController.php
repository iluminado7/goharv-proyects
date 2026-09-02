<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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

            throw ValidationException::withMessages([
                'email' => 'Esos datos no coinciden con ninguna cuenta.',
            ]);
        }

        if (! Auth::user()->is_active) {
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

        return redirect()->intended(route('projects.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
