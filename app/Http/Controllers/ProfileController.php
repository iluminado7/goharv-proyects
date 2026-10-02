<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AvatarImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;
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

    public function updateAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
        ], [
            'avatar.required' => 'Elegí una imagen.',
            'avatar.image'    => 'El archivo tiene que ser una imagen.',
            'avatar.mimes'    => 'Se aceptan JPG, PNG o WebP.',
            'avatar.max'      => 'La imagen no puede pasar de 4 MB.',
        ]);

        try {
            $binario = AvatarImage::fromUpload($request->file('avatar'));
        } catch (RuntimeException) {
            return back()->withErrors(['avatar' => 'No se pudo leer esa imagen. Probá con otra.']);
        }

        $avatar = $request->user()->avatar()->updateOrCreate([], [
            'mime'  => AvatarImage::MIME,
            'image' => $binario,
        ]);

        // Si la imagen nueva da los mismos bytes, Eloquent no ve cambios y no
        // mueve updated_at: la URL quedaria igual y el navegador seguiria
        // mostrando la anterior. Subir una foto siempre renueva la direccion.
        $avatar->touch();

        return redirect()->route('profile.edit')->with('ok', 'Foto actualizada.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        // Se busca el modelo y se borra el modelo: un ->avatar()->delete()
        // borra por query builder y no dispara los eventos, asi que dejaria
        // users.avatar_updated_at apuntando a una foto que ya no existe.
        $request->user()->avatar()->first()?->delete();

        return redirect()->route('profile.edit')->with('ok', 'Foto quitada. Volvés a las iniciales.');
    }

    /**
     * Sirve la foto desde la base. Va con ETag para que el navegador no la
     * pida de nuevo en cada pantalla, y privada para que no la guarde el CDN.
     */
    public function avatar(Request $request, User $user): Response
    {
        $avatar = $user->avatar()->first();

        abort_if($avatar === null, 404);

        $etag = '"'.md5($user->id.'-'.$avatar->updated_at->getTimestamp()).'"';

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304);
        }

        // La URL lleva la fecha de la foto, asi que este contenido no cambia
        // nunca: se puede guardar sin vencimiento. Al cambiar la foto cambia
        // la direccion y el navegador pide la nueva. Privada igual: son caras
        // del equipo y no las tiene que guardar el CDN.
        return response($avatar->image, 200, [
            'Content-Type'  => $avatar->mime,
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'ETag'          => $etag,
        ]);
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
