@php
    $sinLeer = auth()->user()->unreadNotifications()->count();
    $ultimas = auth()->user()->notifications()->latest()->take(6)->get();
@endphp

{{-- <details> es un desplegable nativo de HTML: abre y cierra sin JavaScript.
     El script de abajo solo agrega cerrarlo al hacer clic afuera. --}}
<details class="campana-caja">
    <summary class="campana {{ $sinLeer ? 'con-avisos' : '' }}"
             title="{{ $sinLeer ? $sinLeer.' sin leer' : 'Notificaciones' }}"
             aria-label="{{ $sinLeer ? 'Notificaciones, '.$sinLeer.' sin leer' : 'Notificaciones' }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
            <path d="M13.7 21a2 2 0 0 1-3.4 0"/>
        </svg>
        @if ($sinLeer)
            <span class="campana-n">{{ $sinLeer > 9 ? '9+' : $sinLeer }}</span>
        @endif
    </summary>

    <div class="campana-panel">
        <div class="campana-cab">
            <strong>Notificaciones</strong>
            @if ($sinLeer)
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button class="campana-marcar">Marcar leídas</button>
                </form>
            @endif
        </div>

        <div class="campana-lista">
            @forelse ($ultimas as $n)
                @php
                    $tipo    = \App\Enums\NotificationType::tryFrom($n->data['tipo'] ?? '');
                    $destino = ($n->data['project_id'] ?? null)
                        ? \App\Models\Project::find($n->data['project_id'])
                        : null;
                @endphp

                <a class="campana-item {{ $n->read_at ? '' : 'sin-leer' }}"
                   href="{{ $destino ? route('projects.show', $destino) : route('notifications.index') }}">
                    <span class="aviso-punto" style="background:{{ $tipo?->color() ?? 'var(--muted)' }}"></span>
                    <span>
                        <strong>{{ $tipo?->label() ?? 'Novedad' }}</strong>
                        <span class="campana-proyecto">{{ $n->data['proyecto'] ?? '' }}</span>
                        <span class="campana-cuando">{{ $n->data['actor'] ?? 'Alguien' }} · {{ $n->created_at->diffForHumans() }}</span>
                    </span>
                </a>
            @empty
                <p class="campana-vacio">Sin notificaciones pendientes</p>
            @endforelse
        </div>

        @if ($ultimas->isNotEmpty())
            <a class="campana-todas" href="{{ route('notifications.index') }}">Ver todas</a>
        @endif
    </div>
</details>
