<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(): View
    {
        return view('members.index', [
            'members' => User::orderBy('name')->withCount('ownedProjects')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:80'],
            'email'    => ['required', 'email', 'max:120', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'role'     => ['required', Rule::in(['admin', 'member'])],
        ]);

        User::create($data);

        return back()->with('ok', 'Miembro agregado.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role'      => ['required', Rule::in(['admin', 'member'])],
            'is_active' => ['required', 'boolean'],
        ]);

        // Nadie se saca a si mismo: si se equivoca queda afuera de su propio
        // panel y hace falta otro responsable para volver a entrar.
        if ($user->id === $request->user()->id && ($data['role'] !== 'admin' || ! $data['is_active'])) {
            return back()->withErrors([
                'role' => 'No podés quitarte a vos mismo los permisos ni darte de baja. Pedíselo a otro responsable.',
            ]);
        }

        // Evita que el panel se quede sin ningun responsable activo.
        if ($user->isAdmin() && ($data['role'] !== 'admin' || ! $data['is_active'])) {
            $otros = User::where('role', UserRole::Admin)
                ->where('is_active', true)
                ->where('id', '!=', $user->id)
                ->count();

            if ($otros === 0) {
                return back()->withErrors([
                    'role' => 'Tiene que quedar al menos un responsable activo.',
                ]);
            }
        }

        $user->update($data);

        return back()->with('ok', 'Miembro actualizado.');
    }
}
