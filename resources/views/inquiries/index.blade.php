@extends('layouts.app')
@section('title', 'Consultas web')

@use('App\Support\WebForms')

@section('content')
    @php
        // Al cambiar de formulario se conservan las fechas; los demas filtros
        // no, porque cada uno existe solo en su formulario.
        $base       = request()->only(['desde', 'hasta']);
        $hayFiltros = collect($filters)->filter()->isNotEmpty();
        $contacto   = $kind === 'contacto';
        $inCompany  = $kind === 'in-company';
        $consulting = $kind === 'analizar-empresa';
        $avisame    = $kind === 'avisame';

        $link = fn (string $key) => route('inquiries.index', $key === WebForms::CONTACT ? $base : array_merge($base, ['form' => $key]));

        // Dos grupos: las consultas que hay que responder y los "Avisame",
        // que son listas de interesados (uno por programa).
        $groups = [
            'Consultas' => [
                ['key' => WebForms::CONTACT, 'label' => 'Contacto', 'count' => $contactCount],
                ['key' => WebForms::IN_COMPANY, 'label' => 'Formación In Company', 'count' => $inCompanyCount],
                ['key' => WebForms::CONSULTING, 'label' => 'Analizar mi empresa', 'count' => $consultingCount],
            ],
            'Avisame' => $programs->map(fn ($p) => [
                'key' => $p, 'label' => WebForms::programLabel($p), 'count' => $leadCounts[$p] ?? 0,
            ])->all(),
        ];
        $current = collect($groups)->flatten(1)->firstWhere('key', $form);

        [$titulo, $bajada] = match ($kind) {
            'contacto'         => ['Contacto', 'Consultas del formulario de contacto del sitio (home y /contacto).'],
            'in-company'       => ['Formación In Company', 'Empresas que quieren diseñar un programa de formación a medida (/academy/formacion-in-company).'],
            'analizar-empresa' => ['Analizar mi empresa', 'Consultas de “Quiero analizar mi empresa” de GoHarv Consulting (/consulting).'],
            'avisame'          => [WebForms::programLabel($form), 'Pedidos de “Avisame cuando abra la inscripción”: gente que quiere saber cuándo abre este programa.'],
        };
        $total = $items->total();
    @endphp

    <div class="inq">
        {{-- PC: barra lateral fija. Los contadores respetan las fechas elegidas. --}}
        <aside class="inq-side">
            @include('inquiries.partials.nav', ['groups' => $groups, 'form' => $form, 'link' => $link, 'navLabel' => 'Formularios'])
        </aside>

        <section class="inq-main" aria-labelledby="inq-title">
            {{-- Celular: la misma lista en un desplegable (sin JS: <details>). --}}
            <details class="inq-picker">
                <summary>
                    <span class="inq-picker-l">Formulario</span>
                    <span class="inq-picker-v">{{ $current['label'] ?? $titulo }}</span>
                    <span class="inq-count">{{ $current['count'] ?? $total }}</span>
                </summary>
                @include('inquiries.partials.nav', ['groups' => $groups, 'form' => $form, 'link' => $link, 'navLabel' => 'Formularios (celular)'])
            </details>

            <header class="inq-head">
                <p class="inq-kicker">{{ $avisame ? 'Avisame' : 'Consultas' }}</p>
                <h1 class="page" id="inq-title">{{ $titulo }}</h1>
                <p class="page-sub">{{ $bajada }}</p>
            </header>

            {{-- Sin onchange: la CSP bloquea los manejadores en linea. Se filtra con el boton. --}}
            <form method="GET" action="{{ route('inquiries.index') }}" class="tools inq-tools">
                @unless ($contacto)
                    <input type="hidden" name="form" value="{{ $form }}">
                @endunless
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
                @if ($unidades->isNotEmpty())
                    <label class="inq-f">
                        <span>Unidad</span>
                        <select name="unidad">
                            <option value="">Todas las unidades</option>
                            @foreach ($unidades as $u)
                                <option value="{{ $u }}" @selected($filters['unidad'] === $u)>{{ $u }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                @if ($tamanos->isNotEmpty())
                    <label class="inq-f">
                        <span>Colaboradores</span>
                        <select name="tamano">
                            <option value="">Todos los tamaños</option>
                            @foreach ($tamanos as $t)
                                <option value="{{ $t }}" @selected($filters['tamano'] === $t)>{{ $t }} colaboradores</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                @if ($desafios->isNotEmpty())
                    <label class="inq-f">
                        <span>Desafío</span>
                        <select name="desafio">
                            <option value="">Todos los desafíos</option>
                            @foreach ($desafios as $d)
                                <option value="{{ $d }}" @selected($filters['desafio'] === $d)>{{ WebForms::challengeLabel($d) }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <div class="inq-tools-acts">
                    <button class="btn btn-ghost">Filtrar</button>
                    @if ($hayFiltros)
                        <a class="btn btn-ghost" href="{{ route('inquiries.index', $contacto ? [] : ['form' => $form]) }}">Limpiar</a>
                    @endif
                </div>
            </form>

            <p class="inq-total" role="status">
                {{ $total }} {{ $avisame ? ($total === 1 ? 'pedido' : 'pedidos') : ($total === 1 ? 'consulta' : 'consultas') }}{{ $hayFiltros ? ' con estos filtros' : '' }}
            </p>

            <div class="list">
                @forelse ($items as $item)
                    <article class="msg">
                        <div class="msg-top">
                            <div class="msg-who">
                                @if ($contacto && $item->company)
                                    <p class="empresa">{{ $item->company }}</p>
                                @elseif ($inCompany || $consulting)
                                    <p class="empresa">{{ $item->company }} · {{ $item->role }}</p>
                                @endif
                                <div class="title-line">
                                    <h3>{{ $item->name }}</h3>
                                    @if ($contacto && $item->unit)
                                        <span class="msg-tag">{{ $item->unit }}</span>
                                    @endif
                                    @if ($inCompany)
                                        <span class="msg-tag">{{ $item->team_size }} colaboradores</span>
                                    @endif
                                    @if ($consulting)
                                        <span class="msg-tag msg-tag-strong">{{ WebForms::challengeLabel($item->challenge) }}</span>
                                    @endif
                                    {{-- Idioma del sitio desde el que llegó (las anteriores al cambio no lo tienen) --}}
                                    @if ($item->locale)
                                        <span class="msg-tag msg-lang" title="{{ WebForms::LOCALES[$item->locale] ?? $item->locale }}">{{ strtoupper($item->locale) }}</span>
                                    @endif
                                </div>
                            </div>
                            <time class="msg-when" datetime="{{ $item->created_at->toIso8601String() }}">
                                {{ $item->receivedAt()->translatedFormat('d M Y, H:i') }}
                            </time>
                        </div>

                        <p class="msg-contact">
                            <a href="mailto:{{ $item->email }}">{{ $item->email }}</a>
                            <span aria-hidden="true">·</span>
                            <a href="tel:{{ $item->phone }}">{{ $item->phone }}</a>
                        </p>

                        {{-- pre-line: respeta los saltos de linea sin imprimir HTML crudo --}}
                        @if ($contacto)
                            <p class="msg-text">{{ $item->message }}</p>
                        @endif

                        @if ($inCompany)
                            @if ($item->areas)
                                <div class="msg-block">
                                    <span class="msg-label">Áreas de interés</span>
                                    <ul class="msg-tags">
                                        @foreach ($item->areas as $area)
                                            <li class="msg-tag">{{ $area }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            @if ($item->message)
                                <p class="msg-text"><span class="msg-label">Desafío</span>{{ $item->message }}</p>
                            @endif
                        @endif

                        @if ($consulting)
                            <p class="msg-text"><span class="msg-label">Qué quiere conseguir</span>{{ $item->goal }}</p>
                        @endif

                        @php
                            $meta = array_filter([
                                $contacto ? $item->country : null,
                                $inCompany && $item->modality ? 'modalidad: '.$item->modality : null,
                                $contacto ? null : ($item->newsletter ? 'quiere novedades por mail' : 'sin novedades por mail'),
                                $item->page ? 'desde '.$item->page : null,
                            ]);
                        @endphp
                        @if ($meta)
                            <p class="meta">{{ implode(' · ', $meta) }}</p>
                        @endif
                    </article>
                @empty
                    <div class="empty">
                        <p>
                            @if ($hayFiltros)
                                No llegó nada con ese filtro.
                            @elseif ($avisame)
                                Todavía nadie pidió que le avisen de este programa.
                            @else
                                Todavía no llegaron consultas desde el sitio.
                            @endif
                        </p>
                    </div>
                @endforelse
            </div>

            {{ $items->links('pagination.goharv') }}
        </section>
    </div>
@endsection
