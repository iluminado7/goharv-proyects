<!DOCTYPE html>
<html lang="es" data-theme="{{ $tema }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') — GoHarv</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ \App\Support\Assets::versioned('css/goharv.css') }}">
    @include('partials.pwa-head')
</head>
<body class="{{ $barra === 'angosta' ? 'barra-angosta' : '' }}">
@auth
    @php
        $items = [
            ['proyectos', 'Proyectos', route('projects.index'), request()->routeIs('projects.*')],
        ];
        if (auth()->user()->isInternal()) {
            $items[] = ['equipo', 'Equipo', route('members.index'), request()->routeIs('members.*')];
        }
        if (auth()->user()->isAdmin()) {
            $items[] = ['actividad', 'Actividad', route('activity.index'), request()->routeIs('activity.*')];
        }
        $items[] = ['perfil', 'Mi perfil', route('profile.edit'), request()->routeIs('profile.*')];
    @endphp

    {{-- El checkbox vive afuera del marco porque de el cuelga, por CSS, que la
         barra se despliegue sobre el contenido en pantallas angostas. --}}
    <input type="checkbox" id="abrir-menu" class="sr-only menu-switch">

    <header class="topbar">
        <label for="abrir-menu" class="hamburguesa" aria-label="Abrir el menú">
            <x-icon name="menu" />
        </label>

        <a href="{{ route('projects.index') }}" class="marca">
            <span class="wordmark">GoHarv.<sup>&reg;</sup></span>
            <span class="producto">Project Manager</span>
        </a>

        <div class="topbar-acts">
            @include('partials.theme-toggle')
            @include('partials.notifications-bell')
            <a href="{{ route('profile.edit') }}" class="me" aria-label="Mi perfil"
               title="{{ auth()->user()->name }}">
                <x-avatar :user="auth()->user()" :size="34" />
            </a>
        </div>
    </header>

    <div class="marco">
        <aside class="barra" aria-label="Navegación principal">
            {{-- No es un item del menu: es el boton que lo pliega, y va solo
                 arriba de todo, separado de la navegacion. Sin JS el form
                 recarga; con JS el script de abajo lo hace en el acto. --}}
            <form method="POST" action="{{ route('sidebar.toggle') }}" class="barra-plegar">
                @csrf
                <button class="plegar-btn" aria-expanded="{{ $barra === 'angosta' ? 'false' : 'true' }}"
                        title="{{ $barra === 'angosta' ? 'Ampliar el menú' : 'Plegar el menú' }}"
                        aria-label="{{ $barra === 'angosta' ? 'Ampliar el menú' : 'Plegar el menú' }}">
                    <x-icon name="menu" :size="20" />
                </button>
            </form>

            <nav class="menu">
                @foreach ($items as [$icono, $texto, $url, $activo])
                    <a class="barra-item {{ $activo ? 'on' : '' }}" href="{{ $url }}"
                       title="{{ $texto }}" @if ($activo) aria-current="page" @endif>
                        <x-icon :name="$icono" />
                        <span class="barra-txt">{{ $texto }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="barra-pie">
                <a href="{{ route('profile.edit') }}" class="barra-yo" title="{{ auth()->user()->name }}">
                    <x-avatar :user="auth()->user()" :size="34" />
                    <span class="barra-txt">
                        <strong>{{ auth()->user()->name }}</strong>
                        <span class="rol">{{ auth()->user()->role->label() }}</span>
                    </span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="barra-item" title="Salir" aria-label="Salir">
                        <x-icon name="salir" />
                        <span class="barra-txt">Salir</span>
                    </button>
                </form>
            </div>
        </aside>

        {{-- Tapa el contenido cuando la barra se abre en el celular: tocarla la
             cierra, porque es el <label> del mismo checkbox. --}}
        <label for="abrir-menu" class="barra-telon" aria-hidden="true"></label>

        <div class="lienzo">
            @hasSection('banda')
                <div class="banda">
                    <div class="wrap">@yield('banda')</div>
                </div>
            @endif

            <main class="wrap">
                @if (session('ok'))
                    <p class="flash">{{ session('ok') }}</p>
                @endif
                @yield('content')
            </main>

            <footer class="gh-footer">
                <div class="wrap">
                    <span class="gh-label">GoHarv Project Manager · {{ now()->year }}</span>
                </div>
            </footer>
        </div>
    </div>
@endauth
@include('partials.instant-toggles')
@include('partials.dropdown-close')
@include('partials.password-toggle')
@include('partials.pwa-register')
</body>
</html>
