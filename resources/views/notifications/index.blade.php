@extends('layouts.app')
@section('title', 'Notificaciones')

@section('content')
    <h1 class="page">Notificaciones</h1>
    <p class="page-sub">Lo que otros hicieron en los proyectos donde estás.</p>

    @if (auth()->user()->unreadNotifications->isNotEmpty())
        <form method="POST" action="{{ route('notifications.read-all') }}" style="margin-bottom:16px">
            @csrf
            <button class="btn btn-ghost btn-sm">Marcar todas como leídas</button>
        </form>
    @endif

    <div class="card">
        @forelse ($notificaciones as $n)
            @php
                $tipo    = \App\Enums\NotificationType::tryFrom($n->data['tipo'] ?? '');
                $destino = ($n->data['project_id'] ?? null)
                    ? \App\Models\Project::find($n->data['project_id'])
                    : null;
            @endphp

            <div class="aviso {{ $n->read_at ? '' : 'sin-leer' }}">
                <span class="aviso-punto" style="background:{{ $tipo?->color() ?? 'var(--muted)' }}"></span>

                <span class="aviso-cuerpo">
                    <strong>{{ $tipo?->label() ?? 'Novedad' }}</strong> ·
                    @if ($destino)
                        <a class="to-detail" href="{{ route('projects.show', $destino) }}">{{ $n->data['proyecto'] }}</a>
                    @else
                        {{-- El proyecto pudo haberse borrado: el nombre quedó guardado. --}}
                        {{ $n->data['proyecto'] ?? 'Un proyecto' }}
                    @endif

                    @if ($n->data['detalle'] ?? null)
                        <span class="aviso-detalle">{{ $n->data['detalle'] }}</span>
                    @endif
                </span>

                <span class="aviso-pie">
                    {{ $n->data['actor'] ?? 'Alguien' }} · {{ $n->created_at->diffForHumans() }}
                </span>
            </div>
        @empty
            <p class="hint" style="margin:0">
                Todavía no hay nada. Acá van a aparecer los avisos cuando te asignen
                un proyecto, te sumen a uno, comenten o muevan algo donde estás.
            </p>
        @endforelse
    </div>

    {{ $notificaciones->links('pagination.goharv') }}
@endsection
