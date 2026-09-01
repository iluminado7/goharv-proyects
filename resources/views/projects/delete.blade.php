@extends('layouts.app')
@section('title', 'Borrar '.$project->name)

@section('content')
    <h1 class="page">Borrar para siempre</h1>
    <p class="page-sub">Esto no se puede deshacer. Leé qué se va antes de confirmar.</p>

    <div class="card danger-card">
        <h2 style="margin:0 0 6px;font-size:17px;font-weight:600">{{ $project->name }}</h2>
        <p style="margin:0 0 20px;color:var(--muted);font-size:13.5px">
            {{ $project->owner?->name ?? 'Sin responsable' }} ·
            archivado {{ $project->deleted_at->translatedFormat('d M Y') }}
        </p>

        <p style="margin:0 0 10px">Se borra de la base, junto con:</p>
        <ul class="perdida">
            <li><strong>{{ $project->updates_count }}</strong> movimiento(s) y comentario(s) del historial</li>
            <li><strong>{{ $project->links_count }}</strong> enlace(s)</li>
            <li><strong>{{ $project->collaborators_count }}</strong> colaborador(es) asignado(s)</li>
        </ul>

        <p class="hint" style="margin:16px 0 0">
            Si lo que querés es sacarlo del tablero pero conservar el rastro, no hace
            falta borrarlo: archivado ya no aparece, y se puede restaurar cuando quieras.
        </p>

        <form method="POST" action="{{ route('projects.force-destroy', $project) }}"
              style="border-top:1px solid var(--line);margin-top:22px;padding-top:20px">
            @csrf @method('DELETE')

            <div class="field">
                <label for="confirmacion">Escribí <strong>{{ $project->name }}</strong> para confirmar</label>
                <input id="confirmacion" name="confirmacion" autocomplete="off" required>
                @error('confirmacion') <p class="err">{{ $message }}</p> @enderror
            </div>

            <div class="form-acts">
                <a class="btn btn-ghost" href="{{ route('projects.archived') }}">Cancelar</a>
                <button class="btn btn-danger">Borrar para siempre</button>
            </div>
        </form>
    </div>

    <div style="height:60px"></div>
@endsection
