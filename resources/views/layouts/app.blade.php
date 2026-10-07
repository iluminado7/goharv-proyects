<!DOCTYPE html>
<html lang="es" data-theme="{{ $tema }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') — GoHarv</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;800&family=Inter:wght@400;500;600&family=Montserrat:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ \App\Support\Assets::versioned('css/goharv.css') }}">
    @include('partials.pwa-head')
</head>
<body>
@auth
    <header class="mast">
        <div class="wrap mast-in">
            <div class="mast-nav">
                <a href="{{ route('projects.index') }}"><span class="wordmark">GoHarv.<sup>&reg;</sup></span></a>

                {{-- En el celular los cuatro botones comian media pantalla. Se
                     colapsan detras de este boton, que es un checkbox escondido
                     con su label: CSS puro, y a diferencia de <details> se puede
                     dejar siempre abierto en escritorio con una media query. --}}
                <input type="checkbox" id="abrir-menu" class="sr-only menu-switch">
                <label for="abrir-menu" class="hamburguesa" aria-label="Abrir el menú">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" aria-hidden="true">
                        <path d="M4 7h16M4 12h16M4 17h16"/>
                    </svg>
                </label>

                <nav class="menu">
                    <a class="btn btn-ghost btn-sm {{ request()->routeIs('projects.*') ? 'on' : '' }}"
                       href="{{ route('projects.index') }}">Proyectos</a>
                    <a class="btn btn-ghost btn-sm {{ request()->routeIs('members.*') ? 'on' : '' }}"
                       href="{{ route('members.index') }}">Equipo</a>
                    @if (auth()->user()->isAdmin())
                        <a class="btn btn-ghost btn-sm {{ request()->routeIs('activity.*') ? 'on' : '' }}"
                           href="{{ route('activity.index') }}">Actividad</a>
                    @endif
                    <a class="btn btn-ghost btn-sm {{ request()->routeIs('profile.*') ? 'on' : '' }}"
                       href="{{ route('profile.edit') }}">Mi perfil</a>
                </nav>
            </div>
            <div class="who">
                <a href="{{ route('profile.edit') }}" class="me">
                    <x-avatar :user="auth()->user()" :size="28" />
                    <strong>{{ auth()->user()->name }}</strong>
                </a>
                @include('partials.notifications-bell')
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
@include('partials.dropdown-close')
@include('partials.password-toggle')
@include('partials.pwa-register')
</body>
</html>
