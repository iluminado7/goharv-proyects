@extends('layouts.app')
@section('title', $project->name)

@section('content')
    <h1 class="page">{{ $project->name }}</h1>
    <p class="page-sub">
        {{ $project->owner?->name ?? 'Sin responsable' }} ·
        prioridad {{ mb_strtolower($project->priority->label()) }}
        @if ($project->due_date)
            · entrega {{ $project->due_date->translatedFormat('d \d\e F') }}
        @endif
    </p>

    <div class="card">
        @if ($project->description)
            <p style="margin:0 0 20px;max-width:66ch">{{ $project->description }}</p>
        @endif

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px">
            @foreach ($project->links as $link)
                <a class="btn {{ $loop->first ? '' : 'btn-ghost' }}" href="{{ $link->url }}"
                   target="_blank" rel="noopener" title="{{ $link->host() }}">{{ $link->label }}</a>
            @endforeach
            @can('update', $project)
                <a class="btn btn-ghost" href="{{ route('projects.edit', $project) }}">Editar</a>
            @endcan
        </div>

        @can('move', $project)
        <form method="POST" action="{{ route('projects.status', $project) }}"
              style="border-top:1px solid var(--line);padding-top:20px">
            @csrf @method('PATCH')
            <div class="row-2">
                <div class="field">
                    <label for="status">Mover a</label>
                    <select id="status" name="status">
                        @foreach ($statuses as $s)
                            <option value="{{ $s->value }}" @selected($project->status === $s)>{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="note">Qué pasó</label>
                    <input id="note" name="note" placeholder="Opcional, pero ayuda al resto">
                </div>
            </div>
            <button class="btn btn-sm">Registrar movimiento</button>
        </form>
        @else
        <p class="hint" style="border-top:1px solid var(--line);padding-top:20px;margin:0">
            Estás viendo un proyecto en el que no participás. Para moverlo de estado, pedile
            al responsable que te sume como colaborador.
        </p>
        @endcan
    </div>

    <h2 style="font-size:16px;font-weight:600;margin:32px 0 16px">Historial</h2>
    <ul class="timeline">
        @forelse ($project->updates as $u)
            <li>
                <p style="margin:0">
                    @if ($u->isStatusChange())
                        <strong>{{ $u->status_from?->label() ?? 'Alta' }} → {{ $u->status_to?->label() }}</strong>
                    @endif
                    {{ $u->body }}
                </p>
                <span class="when">{{ $u->author?->name ?? 'Alguien' }} · {{ $u->created_at->translatedFormat('d M Y, H:i') }}</span>
            </li>
        @empty
            <li><p style="margin:0;color:var(--muted)">Todavía no hay movimientos registrados.</p></li>
        @endforelse
    </ul>
    <div style="height:60px"></div>
@endsection
