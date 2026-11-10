@extends('layouts.app')
@section('title', 'Consultas web')

@section('content')
    @php
        $hayFiltros = collect($filters)->filter()->isNotEmpty();
        $total      = $items->total();
    @endphp

    <h1 class="page">Consultas web</h1>
    <p class="page-sub">
        Lo que llega por el formulario de contacto de cacciatoreabogados.com.ar.
        Cada consulta también le llega por mail al estudio: para contestar, se responde ese mail.
    </p>

    {{-- Sin onchange: la CSP bloquea los manejadores en linea. Se filtra con el boton. --}}
    <form method="GET" action="{{ route('inquiries.index') }}" class="tools inq-tools">
        <label class="inq-f inq-f-date">
            <span>Desde</span>
            <input type="date" name="desde" value="{{ $filters['desde'] }}">
        </label>
        <label class="inq-f inq-f-date">
            <span>Hasta</span>
            <input type="date" name="hasta" value="{{ $filters['hasta'] }}">
        </label>
        @if ($empresas->isNotEmpty())
            <label class="inq-f">
                <span>Empresa</span>
                <select name="empresa">
                    <option value="">Todas las empresas</option>
                    @foreach ($empresas as $e)
                        <option value="{{ $e }}" @selected($filters['empresa'] === $e)>{{ $e }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <div class="inq-tools-acts">
            <button class="btn btn-ghost">Filtrar</button>
            @if ($hayFiltros)
                <a class="btn btn-ghost" href="{{ route('inquiries.index') }}">Limpiar</a>
            @endif
        </div>
    </form>

    <p class="inq-total" role="status">
        {{ $total }} {{ $total === 1 ? 'consulta' : 'consultas' }}{{ $hayFiltros ? ' con estos filtros' : '' }}
    </p>

    <div class="list">
        @forelse ($items as $item)
            <article class="msg">
                <div class="msg-top">
                    <div class="msg-who">
                        <p class="empresa">{{ $item->company }}</p>
                        <div class="title-line">
                            <h3>{{ $item->deQuien() }}</h3>
                            @if ($item->locale)
                                <span class="msg-tag msg-lang" title="{{ \App\Models\CacciatoreMessage::LOCALES[$item->locale] ?? $item->locale }}">{{ strtoupper($item->locale) }}</span>
                            @endif
                        </div>
                    </div>
                    <time class="msg-when" datetime="{{ $item->created_at->toIso8601String() }}">
                        {{ $item->created_at->translatedFormat('d M Y, H:i') }}
                    </time>
                </div>

                <p class="msg-contact">
                    <a href="mailto:{{ $item->email }}">{{ $item->email }}</a>
                    <span aria-hidden="true">·</span>
                    <a href="tel:{{ $item->phone }}">{{ $item->phone }}</a>
                </p>

                {{-- pre-line: respeta los saltos de linea sin imprimir HTML crudo --}}
                <p class="msg-text">{{ $item->message }}</p>

                <p class="meta">{{ implode(' · ', array_filter([$item->country, $item->page ? 'desde '.$item->page : null])) }}</p>
            </article>
        @empty
            <div class="empty">
                <p>{{ $hayFiltros ? 'No llegó nada con ese filtro.' : 'Todavía no llegaron consultas desde el sitio.' }}</p>
            </div>
        @endforelse
    </div>

    {{ $items->links('pagination.goharv') }}
@endsection
