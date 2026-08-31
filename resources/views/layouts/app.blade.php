<!DOCTYPE html>
<html lang="es" data-theme="{{ $tema }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') — GoHarv</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/goharv.css') }}">
</head>
<body>
@auth
    <header class="mast">
        <div class="wrap mast-in">
            <div class="mast-nav">
                <a href="{{ route('projects.index') }}"><span class="wordmark">GoHarv.<sup>&reg;</sup></span></a>
                <nav class="menu">
                    <a class="btn btn-ghost btn-sm {{ request()->routeIs('projects.*') ? 'on' : '' }}"
                       href="{{ route('projects.index') }}">Proyectos</a>
                    @if (auth()->user()->isAdmin())
                        <a class="btn btn-ghost btn-sm {{ request()->routeIs('members.*') ? 'on' : '' }}"
                           href="{{ route('members.index') }}">Equipo</a>
                    @endif
                    <a class="btn btn-ghost btn-sm {{ request()->routeIs('profile.*') ? 'on' : '' }}"
                       href="{{ route('profile.edit') }}">Mi perfil</a>
                </nav>
            </div>
            <div class="who">
                <a href="{{ route('profile.edit') }}" class="me"><strong>{{ auth()->user()->name }}</strong></a>
                @include('partials.theme-toggle')
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-ghost btn-sm">Salir</button>
                </form>
            </div>
        </div>
    </header>
@endauth

<main class="wrap">
    @if (session('ok'))
        <p class="flash">{{ session('ok') }}</p>
    @endif
    @yield('content')
</main>
</body>
</html>
