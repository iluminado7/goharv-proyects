<!DOCTYPE html>
<html lang="es" data-theme="{{ $tema }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar — GoHarv</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/goharv.css') }}">
</head>
<body>
<div class="gate">
    <div class="gate-card">
        <div class="gate-theme">@include('partials.theme-toggle')</div>
        <span class="wordmark">GoHarv.<sup>&reg;</sup></span>
        <p class="gate-sub">Panel interno de proyectos</p>

        <form method="POST" action="{{ route('login') }}" class="card">
            @csrf
            <div class="field">
                <label for="email">Correo</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autofocus required>
                @error('email') <p class="err">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label for="password">Clave</label>
                <input id="password" name="password" type="password" required>
                @error('password') <p class="err">{{ $message }}</p> @enderror
            </div>
            <label style="display:flex;gap:8px;align-items:center;font-size:13px;color:var(--muted);margin-bottom:18px">
                <input type="checkbox" name="remember" value="1" style="width:auto"> Mantener la sesión abierta
            </label>
            <button class="btn btn-wide">Entrar</button>
        </form>
    </div>
</div>
</body>
</html>
