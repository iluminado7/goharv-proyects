@extends('layouts.app')
@section('title', 'Mi perfil')

@section('content')
    <h1 class="page">Mi perfil</h1>
    <p class="page-sub">
        {{ $user->role->label() }} ·
        {{ $projects === 1 ? '1 proyecto a cargo' : $projects.' proyectos a cargo' }} ·
        en el panel desde {{ $user->created_at->translatedFormat('F Y') }}
    </p>

    <form method="POST" action="{{ route('profile.update') }}" class="card">
        @csrf @method('PUT')

        <div class="field">
            <label for="name">Nombre</label>
            <input id="name" name="name" value="{{ old('name', $user->name) }}" required>
            @error('name') <p class="err">{{ $message }}</p> @enderror
        </div>

        <div class="field">
            <label for="email">Correo</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
            @error('email') <p class="err">{{ $message }}</p> @enderror
        </div>

        <p class="hint">El rol y el alta o baja de la cuenta los maneja el responsable del panel desde <em>Equipo</em>.</p>

        <div class="form-acts">
            <button class="btn">Guardar</button>
        </div>
    </form>

    <h2 class="section">Cambiar la clave</h2>

    <form method="POST" action="{{ route('profile.password') }}" class="card">
        @csrf @method('PUT')

        <div class="field">
            <label for="current_password">Clave actual</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
            @error('current_password') <p class="err">{{ $message }}</p> @enderror
        </div>

        <div class="row-2">
            <div class="field">
                <label for="password">Clave nueva</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required>
                @error('password') <p class="err">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label for="password_confirmation">Repetirla</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            </div>
        </div>

        <p class="hint">Mínimo 8 caracteres. Si la olvidás, hoy la única salida es pedirle al responsable que la resetee.</p>

        <div class="form-acts">
            <button class="btn">Cambiar clave</button>
        </div>
    </form>

    <div style="height:60px"></div>
@endsection
