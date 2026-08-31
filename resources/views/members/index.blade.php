@extends('layouts.app')
@section('title', 'Equipo')

@section('content')
    <h1 class="page">Equipo</h1>
    <p class="page-sub">Quiénes pueden entrar al panel y con qué permisos.</p>

    <div class="card" style="margin-bottom:28px">
        @foreach ($members as $m)
            <div class="member-row">
                <span>
                    {{ $m->name }}
                    <span class="rol">{{ $m->role->label() }}</span>
                    @unless ($m->is_active) <span class="rol">de baja</span> @endunless
                    <br>
                    <span style="font-size:12.5px;color:var(--faint)">
                        {{ $m->email }} · {{ $m->owned_projects_count }} proyecto(s) a cargo
                    </span>
                </span>

                <form method="POST" action="{{ route('members.update', $m) }}"
                      style="display:flex;gap:8px;align-items:center">
                    @csrf @method('PATCH')
                    <select name="role">
                        <option value="member" @selected($m->role->value === 'member')>Miembro</option>
                        <option value="admin"  @selected($m->role->value === 'admin')>Responsable</option>
                    </select>
                    <select name="is_active">
                        <option value="1" @selected($m->is_active)>Activo</option>
                        <option value="0" @selected(! $m->is_active)>De baja</option>
                    </select>
                    <button class="btn btn-ghost btn-sm">Guardar</button>
                </form>
            </div>
        @endforeach
        @error('role') <p class="err" style="color:#E05C4B;font-size:13px">{{ $message }}</p> @enderror
    </div>

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
                <input id="password" name="password" type="text" required>
                @error('password') <p class="err">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label for="new-role">Permisos</label>
                <select id="new-role" name="role">
                    <option value="member">Miembro</option>
                    <option value="admin">Responsable</option>
                </select>
            </div>
        </div>
        <button class="btn">Sumar al equipo</button>
    </form>
    <div style="height:60px"></div>
@endsection
