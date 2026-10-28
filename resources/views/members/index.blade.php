@extends('layouts.app')
@section('title', 'Equipo')

@section('content')
    <h1 class="page">Equipo</h1>
    <p class="page-sub">
        {{ $administra
            ? 'Quiénes pueden entrar al panel. Los datos de una cuenta los edita un responsable.'
            : 'Quiénes están en el panel. Los datos y los permisos los maneja el responsable.' }}
    </p>

    <div class="card" style="margin-bottom:28px">
        @foreach ($members as $m)
            <div class="member-row">
                <span class="member-id">
                    <x-avatar :user="$m" :size="40" />
                    <span>
                        {{ $m->name }}
                        <span class="rol">{{ $m->role->label() }}</span>
                        <br>
                        <span style="font-size:12.5px;color:var(--faint)">
                            {{ $m->email }} · {{ $m->owned_projects_count }} proyecto(s) a cargo
                        </span>
                    </span>
                </span>

                <span class="member-acts">
                    @if ($administra)
                        <a class="btn btn-ghost btn-sm" href="{{ route('members.edit', $m) }}">Editar</a>
                    @endif

                    {{-- Dar de baja no borra nada: saca la cuenta del panel y de
                         todos los desplegables. Por eso el aviso es explícito. --}}
                    @can('setAccess', $m)
                        <form method="POST" action="{{ route('members.access', $m) }}"
                              onsubmit="return confirm('¿Dar de baja a {{ $m->name }}? Pierde el acceso al panel y no va a poder sumarse a proyectos nuevos. Lo que hizo queda donde está, y lo podés reactivar después.')">
                            @csrf @method('PATCH')
                            <input type="hidden" name="is_active" value="0">
                            <button class="btn btn-danger btn-sm">Dar de baja</button>
                        </form>
                    @endcan
                </span>
            </div>
        @endforeach
        @error('is_active') <p class="err">{{ $message }}</p> @enderror
    </div>

    @if ($bajas->isNotEmpty())
        <h2 class="section">Dados de baja</h2>
        <p class="hint">
            No entran al panel ni aparecen al elegir responsable, colaboradores o a
            quién asignarle una tarea. Lo que hicieron sigue en su lugar.
        </p>

        <div class="card" style="margin-bottom:28px">
            @foreach ($bajas as $m)
                <div class="member-row">
                    <span class="member-id">
                        <x-avatar :user="$m" :size="40" />
                        <span>
                            {{ $m->name }}
                            <span class="rol">{{ $m->role->label() }}</span>
                            <br>
                            <span style="font-size:12.5px;color:var(--faint)">
                                {{ $m->email }} · {{ $m->owned_projects_count }} proyecto(s) a cargo
                            </span>
                        </span>
                    </span>

                    <span class="member-acts">
                        @if ($administra)
                            <a class="btn btn-ghost btn-sm" href="{{ route('members.edit', $m) }}">Editar</a>
                        @endif
                        <form method="POST" action="{{ route('members.access', $m) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="is_active" value="1">
                            <button class="btn btn-ghost btn-sm">Reactivar</button>
                        </form>
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    @if ($administra)
        <form method="POST" action="{{ route('members.store') }}" class="card">
            @csrf
            <h2 style="margin:0 0 18px;font-size:16px;font-weight:600">Sumar a alguien</h2>
            <div class="row-2">
                <div class="field">
                    <label for="name">Nombre</label>
                    <input id="name" name="name" value="{{ old('name') }}" required>
                    @error('name') <p class="err">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="email">Correo</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required>
                    @error('email') <p class="err">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="row-2">
                <div class="field">
                    <label for="password">Clave inicial</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required>
                    @error('password') <p class="err">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="new-role">Permisos</label>
                    <select id="new-role" name="role">
                        @foreach ($roles as $rol)
                            <option value="{{ $rol->value }}" @selected(old('role') === $rol->value)>{{ $rol->label() }}</option>
                        @endforeach
                    </select>
                    <p class="hint" style="margin-top:7px">
                        El profesional externo solo entra a los proyectos que le deleguen:
                        no ve el tablero completo, ni esta pantalla, ni la actividad.
                    </p>
                </div>
            </div>
            <button class="btn">Sumar al equipo</button>
        </form>
    @else
        <p class="hint">
            Podés dar de baja a un profesional externo cuando termine su trabajo. Para
            sumar a alguien o cambiar permisos, pedíselo a un responsable del panel.
        </p>
    @endif

    <div style="height:60px"></div>
@endsection
