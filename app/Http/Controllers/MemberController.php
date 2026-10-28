<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\User;
use App\Notifications\Bienvenida;
use App\Support\Avisos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $quien      = $request->user();
        $administra = $quien->isAdmin();

        $todos = User::query()->orderBy('name')->withCount('ownedProjects')->get();

        // Las cuentas dadas de baja salen de la lista y van a su propia
        // seccion: la de arriba es "quien esta", y mezclar las dos obligaba a
        // leer el cartelito de cada fila para saber a quien se le puede pedir
        // algo. Abajo solo aparecen las que esa persona puede reactivar.
        return view('members.index', [
            'members'    => $todos->where('is_active', true)->values(),
            'bajas'      => $todos
                ->where('is_active', false)
                ->filter(fn (User $u) => $quien->can('viewInactive', $u))
                ->values(),
            'administra' => $administra,
            'roles'      => UserRole::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:80'],
            'email'    => ['required', 'email', 'max:120', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'role'     => ['required', Rule::in(UserRole::values())],
        ]);

        // Nace obligada a cambiar la clave: la que va en el correo tiene que
        // servir una sola vez.
        $user = User::create($data + ['must_change_password' => true]);

        Avisos::mandar([$user], new Bienvenida($data['password'], $request->user()));

        return back()->with('ok', Avisos::hayCorreo()
            ? 'Cuenta creada. Le mandamos los datos a '.$user->email.'.'
            : 'Cuenta creada. El envío de correos todavía no está activo: pasale vos '
              .'el usuario y la clave. Al entrar le va a pedir que la cambie.');
    }

    /** Los datos de una cuenta los edita un responsable y nadie mas. */
    public function edit(User $user): View
    {
        return view('members.edit', [
            'miembro' => $user,
            'roles'   => UserRole::cases(),
        ]);
    }

    /**
     * Nombre, correo, rol y, si hace falta, una clave nueva. La clave la pone
     * un responsable solo cuando alguien la olvida: mientras no haya
     * recuperacion por correo, es la unica salida, y hacerlo desde aca es
     * mejor que tocar la base a mano.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:80'],
            'email'    => ['required', 'email', 'max:120', Rule::unique('users')->ignore($user)],
            'role'     => ['required', Rule::in(UserRole::values())],
            'password' => ['nullable', 'string', Password::defaults()],
        ], [
            'email.unique' => 'Ya hay una cuenta con ese correo.',
        ]);

        // Nadie se saca a si mismo: si se equivoca queda afuera de su propio
        // panel y hace falta otro responsable para volver a entrar.
        if ($user->id === $request->user()->id && $data['role'] !== 'admin') {
            return back()->withErrors([
                'role' => 'No podés quitarte a vos mismo los permisos. Pedíselo a otro responsable.',
            ])->withInput();
        }

        if ($data['role'] !== 'admin' && $this->esElUltimoResponsable($user)) {
            return back()->withErrors([
                'role' => 'Tiene que quedar al menos un responsable activo.',
            ])->withInput();
        }

        $clave = $data['password'] ?? null;
        unset($data['password']);

        $cambios = $this->queCambio($user, $data, $clave !== null);

        // Si un responsable le pone una clave, es la misma situacion que el
        // alta: la eligio otro y hay que cambiarla al entrar.
        $user->update($clave
            ? $data + ['password' => $clave, 'must_change_password' => true]
            : $data);

        if ($cambios !== []) {
            Activity::anotar(
                ActivityAction::CuentaEditada, $request->user(),
                subject: $user->name, detail: implode(', ', $cambios),
            );
        }

        return redirect()
            ->route('members.index')
            ->with('ok', 'Datos de '.$user->name.' actualizados.');
    }

    /** Para que la bitacora diga que se toco, y no solo que hubo una edicion. */
    private function queCambio(User $user, array $data, bool $claveNueva): array
    {
        $cambios = [];

        if ($user->name !== $data['name']) {
            $cambios[] = 'nombre';
        }
        if ($user->email !== $data['email']) {
            $cambios[] = 'correo';
        }
        if ($user->role->value !== $data['role']) {
            $cambios[] = 'rol: '.UserRole::from($data['role'])->label();
        }
        if ($claveNueva) {
            $cambios[] = 'clave restablecida';
        }

        return $cambios;
    }

    /**
     * Dar de baja o reactivar. Lo puede hacer un responsable con cualquiera, y
     * un socio con un profesional externo: el que lo contrató es quien sabe
     * cuándo dejó de trabajar con nosotros.
     */
    public function access(Request $request, User $user): RedirectResponse
    {
        $this->authorize('setAccess', $user);

        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        $activa = (bool) $data['is_active'];

        if (! $activa && $this->esElUltimoResponsable($user)) {
            return back()->withErrors([
                'is_active' => 'Tiene que quedar al menos un responsable activo.',
            ]);
        }

        if ($user->is_active === $activa) {
            return back();
        }

        $user->update(['is_active' => $activa]);

        Activity::anotar(
            $activa ? ActivityAction::CuentaReactivada : ActivityAction::CuentaDadaDeBaja,
            $request->user(),
            subject: $user->name,
            detail: $user->role->label(),
        );

        return back()->with('ok', $activa
            ? $user->name.' vuelve a tener acceso.'
            : $user->name.' quedó fuera del panel. Lo que hizo sigue en su lugar.');
    }

    /**
     * Evita que el panel se quede sin nadie que pueda administrarlo.
     *
     * Hoy no hay forma de llegar aca: el unico que podria dejar el panel sin
     * responsable activo es el propio responsable, y eso ya lo frena antes la
     * regla de que nadie se saca a si mismo. Queda igual, porque es la
     * invariante que importa y cualquier ruta nueva que toque role o
     * is_active tiene que respetarla.
     */
    private function esElUltimoResponsable(User $user): bool
    {
        if (! $user->isAdmin() || ! $user->is_active) {
            return false;
        }

        return ! User::where('role', UserRole::Admin)
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->exists();
    }
}
