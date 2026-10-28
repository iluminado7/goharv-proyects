{{-- Pantalla cerrada: hasta que la clave cambie, el panel no se abre. Por eso
     no usa layouts.app —no tendría sentido mostrar un menú que no lleva a
     ningún lado— y repite la forma de la pantalla de ingreso. --}}
<!DOCTYPE html>
<html lang="es" data-theme="{{ $tema }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Elegí tu clave — GoHarv</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ \App\Support\Assets::versioned('css/goharv.css') }}">
    @include('partials.pwa-head')
</head>
<body>
<div class="gate">
    <div class="gate-card">
        <div class="gate-theme">@include('partials.theme-toggle')</div>
        <span class="wordmark">GoHarv.<sup>&reg;</sup></span>
        <p class="gate-sub">
            Hola {{ auth()->user()->name }}. Antes de entrar, elegí una clave tuya:
            la que te llegó por correo queda escrita en tu casilla y deja de servir
            ahora mismo.
        </p>

        <form method="POST" action="{{ route('password.change.store') }}" class="card">
            @csrf
            <div class="field">
                <label for="current_password">Clave que te mandamos</label>
                <input id="current_password" name="current_password" type="password"
                       autocomplete="current-password" autofocus required>
                @error('current_password') <p class="err">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label for="password">Clave nueva</label>
                <input id="password" name="password" type="password"
                       autocomplete="new-password" required>
                @error('password') <p class="err">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label for="password_confirmation">Repetila</label>
                <input id="password_confirmation" name="password_confirmation" type="password"
                       autocomplete="new-password" required>
            </div>
            <button class="btn btn-wide">Guardar y entrar</button>
        </form>

        <form method="POST" action="{{ route('logout') }}" style="margin-top:14px;text-align:center">
            @csrf
            <button class="borrar-nota">Salir</button>
        </form>
    </div>
</div>
@include('partials.instant-toggles')
@include('partials.password-toggle')
@include('partials.pwa-register')
</body>
</html>
