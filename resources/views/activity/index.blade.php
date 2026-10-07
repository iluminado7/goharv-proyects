@extends('layouts.app')
@section('title', 'Actividad')

@section('content')
    <h1 class="page">Actividad</h1>
    <p class="page-sub">
        Quién hizo qué y cuándo, en todo el panel.
        @if ($fallosSemana > 0)
            · <span class="late">{{ $fallosSemana }} intento(s) de ingreso fallido(s) esta semana</span>
        @endif
    </p>

    @php ($sinTipo = request()->except(['tipo', 'page']))

    <nav class="tally" aria-label="Filtrar actividad">
        @foreach ([
            '' => 'Todo',
            'acceso' => 'Accesos',
            'fallos' => 'Fallidos',
            'proyectos' => 'Proyectos',
        ] as $valor => $texto)
            @php ($activo = ($filters['tipo'] ?? '') === $valor)
            <a class="tally-item {{ $activo ? 'on' : '' }}"
               href="{{ route('activity.index', $valor === '' ? $sinTipo : array_merge($sinTipo, ['tipo' => $valor])) }}"
               @if ($activo) aria-current="true" @endif>
                <span class="l" style="font-size:14px">{{ $texto }}</span>
            </a>
        @endforeach
    </nav>

    <form method="GET" class="tools">
        <select name="action" onchange="this.form.submit()">
            <option value="">Cualquier acción</option>
            @foreach ($acciones as $a)
                <option value="{{ $a->value }}" @selected(($filters['action'] ?? null) === $a->value)>{{ $a->label() }}</option>
            @endforeach
        </select>
        <select name="user" onchange="this.form.submit()">
            <option value="">Cualquier persona</option>
            @foreach ($members as $m)
                <option value="{{ $m->id }}" @selected(($filters['user'] ?? null) == $m->id)>{{ $m->name }}</option>
            @endforeach
        </select>
        @if ($filters['tipo'] ?? null)
            <input type="hidden" name="tipo" value="{{ $filters['tipo'] }}">
        @endif
        <button class="btn btn-ghost">Filtrar</button>
    </form>

    <div class="card">
        @forelse ($activities as $a)
            <div class="log-fila">
                <span class="log-punto" style="background:{{ $a->action->color() }}"></span>

                <span class="log-cuerpo">
                    <strong>{{ $a->action->label() }}</strong>
                    @if ($a->project)
                        · <a class="to-detail" href="{{ route('projects.show', $a->project) }}">{{ $a->project->name }}</a>
                    @elseif ($a->subject && ! $a->action->esFallo())
                        · {{ $a->subject }}
                    @endif

                    @if ($a->detail)
                        <span class="log-detalle">{{ $a->detail }}</span>
                    @endif
                </span>

                <span class="log-pie">
                    {{ $a->quien() }} · {{ $a->created_at->translatedFormat('d M Y, H:i') }}
                    @if ($a->ip) · {{ $a->ip }} @endif
                </span>
            </div>
        @empty
            <p class="hint" style="margin:0">No hay actividad registrada con ese filtro.</p>
        @endforelse
    </div>

    {{ $activities->links('pagination.goharv') }}
@endsection
