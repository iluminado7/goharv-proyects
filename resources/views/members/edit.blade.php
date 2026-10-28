@extends('layouts.app')
@section('title', 'Editar '.$miembro->name)

@section('content')
    <h1 class="page">Editar cuenta</h1>
    <p class="page-sub">
        {{ $miembro->email }} ·
        {{ $miembro->is_active ? 'activa' : 'dada de baja' }}
    </p>

    <form method="POST" action="{{ route('members.update', $miembro) }}" class="card">
        @csrf @method('PATCH')

        <div class="foto-fila" style="margin-bottom:24px">
            <x-avatar :user="$miembro" :size="64" />
            <div class="foto-datos">
                <p class="hint" style="margin:0">
                    La foto la cambia cada uno desde <em>Mi perfil</em>. Desde acá se
                    editan los datos de la cuenta.
                </p>
            </div>
        </div>

        <div class="row-2">
            <div class="field">
                <label for="name">Nombre</label>
                <input id="name" name="name" value="{{ old('name', $miembro->name) }}" required>
                @error('name') <p class="err">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label for="email">Correo</label>
                <input id="email" name="email" type="email"
                       value="{{ old('email', $miembro->email) }}" required>
                @error('email') <p class="err">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="field">
            <label for="role">Permisos</label>
            <select id="role" name="role">
                @foreach ($roles as $rol)
                    <option value="{{ $rol->value }}" @selected(old('role', $miembro->role->value) === $rol->value)>{{ $rol->label() }}</option>
                @endforeach
            </select>
            @error('role') <p class="err">{{ $message }}</p> @enderror
        </div>

        {{-- Mientras no haya recuperación por correo, esta es la única salida
             para quien olvida la clave. Va en blanco: si no se escribe nada, la
             clave no se toca. --}}
        <div class="field">
            <label for="password">Clave nueva</label>
            <p class="hint">
                Solo si la olvidó. Dejalo vacío para no cambiarla. Avisale por otro
                medio y pedile que la cambie desde <em>Mi perfil</em>.
            </p>
            <input id="password" name="password" type="password" autocomplete="new-password">
            @error('password') <p class="err">{{ $message }}</p> @enderror
        </div>

        <div class="form-acts">
            <a class="btn btn-ghost btn-sm left" href="{{ route('members.index') }}">Volver</a>
            <button class="btn btn-sm">Guardar</button>
        </div>
    </form>

    <div style="height:60px"></div>
@endsection
