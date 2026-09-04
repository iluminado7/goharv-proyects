@extends('layouts.app')
@section('title', 'Proyectos')

@section('content')
    @php ($sinEstado = request()->except(['status', 'page']))

    {{-- Los contadores son el filtro por estado: muestran cuantos hay y llevan
         ahi con un clic. Reemplazan a la fila de chips, que hacia lo mismo. --}}
    <nav class="tally" aria-label="Filtrar por estado">
        <a class="tally-item {{ empty($filters['status']) ? 'on' : '' }}"
           href="{{ route('projects.index', $sinEstado) }}"
           @if (empty($filters['status'])) aria-current="true" @endif>
            <span class="n">{{ $total }}</span><span class="l">proyectos</span>
        </a>

        @foreach ($statuses as $s)
            @php ($activo = ($filters['status'] ?? null) === $s->value)
            <a class="tally-item {{ $activo ? 'on' : '' }}"
               href="{{ route('projects.index', array_merge($sinEstado, ['status' => $s->value])) }}"
               @if ($activo) aria-current="true" @endif>
                <span class="n" style="color:{{ $s->color() }}">{{ $counts[$s->value] ?? 0 }}</span>
                <span class="l">{{ mb_strtolower($s->label()) }}</span>
            </a>
        @endforeach
    </nav>

    <form method="GET" class="tools">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
               placeholder="Buscar por nombre o detalle">
        @if ($empresas->isNotEmpty())
            <select name="client" onchange="this.form.submit()">
                <option value="">Todas las empresas</option>
                @foreach ($empresas as $empresa)
                    <option value="{{ $empresa }}" @selected(($filters['client'] ?? null) === $empresa)>{{ $empresa }}</option>
                @endforeach
            </select>
        @endif
        <select name="owner" onchange="this.form.submit()">
            <option value="">Todo el equipo</option>
            @foreach ($members as $m)
                <option value="{{ $m->id }}" @selected(($filters['owner'] ?? null) == $m->id)>{{ $m->name }}</option>
            @endforeach
        </select>
        <select name="sort" onchange="this.form.submit()">
            <option value="prioridad" @selected($sort === 'prioridad')>Ordenar por prioridad</option>
            <option value="estado"    @selected($sort === 'estado')>Ordenar por estado</option>
            <option value="reciente"  @selected($sort === 'reciente')>Últimos movimientos</option>
            <option value="nombre"    @selected($sort === 'nombre')>Orden alfabético</option>
        </select>
        <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
        <button class="btn btn-ghost">Buscar</button>
        <a class="btn" href="{{ route('projects.create') }}">Agregar proyecto</a>
    </form>

    <div class="chips">

        @if ($archivados > 0)
            <a class="chip chip-off" href="{{ route('projects.archived') }}">
                Archivados <span class="chip-n">{{ $archivados }}</span>
            </a>
        @endif
    </div>

    <div class="list">
        @forelse ($projects as $project)
            @include('projects.row', ['project' => $project])
        @empty
            <div class="empty">
                <p>{{ request()->hasAny(['q','status','owner']) ? 'Ningún proyecto coincide con ese filtro.' : 'Todavía no hay proyectos cargados. Empezá por el que más te apura.' }}</p>
                <a class="btn" href="{{ route('projects.create') }}">Agregar proyecto</a>
            </div>
        @endforelse
    </div>

    {{ $projects->links('pagination.goharv') }}
@endsection
