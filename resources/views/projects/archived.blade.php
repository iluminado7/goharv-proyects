@extends('layouts.app')
@section('title', 'Archivados')

@section('content')
    <h1 class="page">Archivados</h1>
    <p class="page-sub">
        Salieron del tablero pero no se borraron: siguen con su historial completo
        y vuelven con un clic.
    </p>

    <form method="GET" class="tools">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
               placeholder="Buscar por nombre o detalle">
        <button class="btn btn-ghost">Buscar</button>
        <a class="btn btn-ghost" href="{{ route('projects.index') }}">Volver al tablero</a>
    </form>

    <div class="list">
        @forelse ($projects as $project)
            <article class="item item-off">
                <div class="pbar" style="background:var(--track)"></div>

                <div class="body">
                    <div class="title-line">
                        <h3>{{ $project->name }}</h3>
                        <span class="prio">{{ $project->status->label() }}</span>
                    </div>

                    @if ($project->description)
                        <p class="desc">{{ Str::limit($project->description, 160) }}</p>
                    @endif

                    <p class="meta">
                        {{ $project->owner?->name ?? 'Sin responsable' }} ·
                        archivado {{ $project->deleted_at->translatedFormat('d M Y') }}
                    </p>
                </div>

                <div class="acts">
                    @can('restore', $project)
                        <form method="POST" action="{{ route('projects.restore', $project) }}">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm">Restaurar</button>
                        </form>
                    @else
                        <span class="hint" style="margin:0">Lo restaura su responsable</span>
                    @endcan
                </div>
            </article>
        @empty
            <div class="empty">
                <p>{{ ($filters['q'] ?? null) ? 'Ningún archivado coincide con esa búsqueda.' : 'No hay proyectos archivados.' }}</p>
                <a class="btn" href="{{ route('projects.index') }}">Volver al tablero</a>
            </div>
        @endforelse
    </div>

    {{ $projects->links('pagination.goharv') }}
@endsection
