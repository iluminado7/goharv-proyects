@extends('layouts.app')
@section('title', 'Consultas web')

@use('App\Support\WebForms')

@section('content')
    @php
        // Al cambiar de formulario se conservan las fechas; empresa y unidad
        // no, porque solo existen en Contacto.
        $base       = request()->only(['desde', 'hasta']);
        $hayFiltros = $filters['desde'] || $filters['hasta'] || $filters['empresa'] || $filters['unidad'];
    @endphp

    {{-- Igual que en el tablero: los contadores son el selector. Cada uno lleva
         a su formulario y muestra cuantos llegaron (en las fechas elegidas). --}}
    <nav class="tally" aria-label="Elegir formulario">
        <a class="tally-item {{ $contacto ? 'on' : '' }}"
           href="{{ route('inquiries.index', $base) }}"
           @if ($contacto) aria-current="true" @endif>
            <span class="n">{{ $contactCount }}</span><span class="l">contacto</span>
        </a>

        @foreach ($programs as $program)
            @php ($activo = $form === $program)
            <a class="tally-item {{ $activo ? 'on' : '' }}"
               href="{{ route('inquiries.index', array_merge($base, ['form' => $program])) }}"
               @if ($activo) aria-current="true" @endif>
                <span class="n">{{ $leadCounts[$program] ?? 0 }}</span>
                <span class="l">avisame · {{ WebForms::programLabel($program) }}</span>
            </a>
        @endforeach
    </nav>

    @if ($contacto)
        <h1 class="page">Contacto</h1>
        <p class="page-sub">Consultas del formulario de contacto del sitio (home y /contacto).</p>
    @else
        <h1 class="page">{{ WebForms::programLabel($form) }}</h1>
        <p class="page-sub">Pedidos de “Avisame cuando abra la inscripción”: gente que quiere saber cuándo abre este programa.</p>
    @endif

    {{-- Sin onchange: la CSP bloquea los manejadores en linea. Se filtra con el boton. --}}
    <form method="GET" action="{{ route('inquiries.index') }}" class="tools">
        @unless ($contacto)
            <input type="hidden" name="form" value="{{ $form }}">
        @endunless
        <label class="tools-date">
            <span>Desde</span>
            <input type="date" name="desde" value="{{ $filters['desde'] }}">
        </label>
        <label class="tools-date">
            <span>Hasta</span>
            <input type="date" name="hasta" value="{{ $filters['hasta'] }}">
        </label>
        @if ($contacto && $empresas->isNotEmpty())
            <select name="empresa" aria-label="Empresa">
                <option value="">Todas las empresas</option>
                @foreach ($empresas as $e)
                    <option value="{{ $e }}" @selected($filters['empresa'] === $e)>{{ $e }}</option>
                @endforeach
            </select>
        @endif
        @if ($contacto && $unidades->isNotEmpty())
            <select name="unidad" aria-label="Unidad de negocio">
                <option value="">Todas las unidades</option>
                @foreach ($unidades as $u)
                    <option value="{{ $u }}" @selected($filters['unidad'] === $u)>{{ $u }}</option>
                @endforeach
            </select>
        @endif
        <button class="btn btn-ghost">Filtrar</button>
        @if ($hayFiltros)
            <a class="btn btn-ghost" href="{{ route('inquiries.index', $contacto ? [] : ['form' => $form]) }}">Limpiar</a>
        @endif
    </form>

    <div class="list">
        @forelse ($items as $item)
            <article class="msg">
                @if ($contacto && $item->company)
                    <p class="empresa">{{ $item->company }}</p>
                @endif

                <div class="title-line">
                    <h3>{{ $item->name }}</h3>
                    @if ($contacto && $item->unit)
                        <span class="msg-tag">{{ $item->unit }}</span>
                    @endif
                </div>

                <p class="msg-contact">
                    <a href="mailto:{{ $item->email }}">{{ $item->email }}</a>
                    <span aria-hidden="true">·</span>
                    <a href="tel:{{ $item->phone }}">{{ $item->phone }}</a>
                </p>

                @if ($contacto)
                    {{-- pre-line: respeta los saltos de linea sin imprimir HTML crudo --}}
                    <p class="msg-text">{{ $item->message }}</p>
                @endif

                <p class="meta">
                    <time datetime="{{ $item->created_at->toIso8601String() }}">
                        {{ $item->receivedAt()->translatedFormat('d M Y, H:i') }}
                    </time>
                    @if ($contacto && $item->country) · {{ $item->country }} @endif
                    @unless ($contacto)
                        · {{ $item->newsletter ? 'quiere novedades por mail' : 'sin novedades por mail' }}
                    @endunless
                    @if ($item->page) · desde {{ $item->page }} @endif
                </p>
            </article>
        @empty
            <div class="empty">
                <p>
                    @if ($hayFiltros)
                        No llegó nada con ese filtro.
                    @elseif ($contacto)
                        Todavía no llegaron consultas desde el sitio.
                    @else
                        Todavía nadie pidió que le avisen de este programa.
                    @endif
                </p>
            </div>
        @endforelse
    </div>

    {{ $items->links('pagination.goharv') }}
@endsection
