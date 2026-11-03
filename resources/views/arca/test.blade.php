@extends('layouts.app')
@section('title', 'Facturación')

@section('content')
    <span class="gh-label">En pruebas · no guarda nada</span>
    <h1 class="page">Pruebas de ARCA</h1>
    <p class="page-sub">
        {{ $config['production'] ? 'PRODUCCIÓN' : 'Homologación' }} ·
        CUIT {{ $config['cuit'] }} ·
        punto de venta {{ $config['pto_vta'] }} ·
        comprobante tipo {{ $config['cbte_tipo'] }} (Factura C) ·
        token {{ $config['access_token'] ? 'cargado' : 'FALTA' }}
    </p>

    @if ($config['production'])
        <p class="flash" style="border-color:var(--error);color:var(--error)">
            ARCA_PRODUCTION está en true. Lo que emitas acá son comprobantes fiscales reales.
        </p>
    @endif

    {{-- --- Conexión ----------------------------------------------------- --}}
    <h2 class="section">Conexión</h2>

    <div class="card">
        <p class="hint">
            Cada llamada a ARCA tarda entre dos y nueve segundos, así que no se
            consulta sola al abrir la página.
        </p>

        <form method="GET">
            <input type="hidden" name="conexion" value="1">
            <button class="btn btn-ghost btn-sm">Probar conexión</button>
        </form>

        @isset($conexion)
            <x-arca-resultado :respuesta="$conexion" />
        @endisset
    </div>

    {{-- --- Emitir -------------------------------------------------------- --}}
    <h2 class="section">Emitir una factura</h2>

    <form method="POST" action="{{ route('arca.test.emitir') }}" class="card">
        @csrf

        <div class="row-2">
            <div class="field">
                <label for="importe">Importe total</label>
                <input id="importe" name="importe" type="number" step="0.01" min="0.01"
                       value="{{ old('importe', 1500) }}" required>
                @error('importe') <p class="err">{{ $message }}</p> @enderror
            </div>
            <div class="field">
                <label for="concepto">Concepto</label>
                <select id="concepto" name="concepto">
                    <option value="1" @selected(old('concepto') == 1)>1 · Productos</option>
                    <option value="2" @selected(old('concepto', 2) == 2)>2 · Servicios</option>
                    <option value="3" @selected(old('concepto') == 3)>3 · Productos y servicios</option>
                </select>
            </div>
        </div>

        <div class="row-2">
            <div class="field">
                <label for="doc_tipo">Tipo de documento</label>
                <select id="doc_tipo" name="doc_tipo">
                    <option value="99" @selected(old('doc_tipo', 99) == 99)>99 · Consumidor final sin identificar</option>
                    <option value="80" @selected(old('doc_tipo') == 80)>80 · CUIT</option>
                    <option value="96" @selected(old('doc_tipo') == 96)>96 · DNI</option>
                </select>
            </div>
            <div class="field">
                <label for="doc_nro">Número de documento</label>
                <input id="doc_nro" name="doc_nro" type="number" value="{{ old('doc_nro', 0) }}">
                <p class="hint">0 si es consumidor final sin identificar.</p>
            </div>
        </div>

        <div class="row-2">
            <div class="field">
                <label for="cond_iva">Condición de IVA del receptor</label>
                <select id="cond_iva" name="cond_iva">
                    <option value="5" @selected(old('cond_iva', 5) == 5)>5 · Consumidor final</option>
                    <option value="1" @selected(old('cond_iva') == 1)>1 · Responsable inscripto</option>
                    <option value="6" @selected(old('cond_iva') == 6)>6 · Monotributo</option>
                    <option value="4" @selected(old('cond_iva') == 4)>4 · Exento</option>
                </select>
            </div>
            <div class="field">
                <label for="fecha">Fecha del comprobante</label>
                <input id="fecha" name="fecha" inputmode="numeric" pattern="\d{8}"
                       placeholder="{{ $hoy }}" value="{{ old('fecha') }}">
                {{-- El sandbox es compartido y alguien puede haber emitido con
                     fecha futura. Como los comprobantes van en orden de fecha
                     no decreciente, ahí hay que igualar esa fecha o ARCA
                     devuelve 10016. --}}
                <p class="hint">
                    Vacío = hoy ({{ $hoy }}). En formato AAAAMMDD. Si da error 10016,
                    probá con la fecha del último comprobante autorizado: no se puede
                    emitir con una fecha anterior a esa.
                </p>
                @error('fecha') <p class="err">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="form-acts">
            <button class="btn btn-sm">Emitir</button>
        </div>

        @isset($resultado)
            <x-arca-resultado :respuesta="$resultado" />
        @endisset
    </form>

    {{-- --- Consultar ----------------------------------------------------- --}}
    <h2 class="section">Consultar un comprobante</h2>

    <div class="card">
        <form method="GET">
            <div class="field">
                <label for="numero">Número de comprobante</label>
                <input id="numero" name="numero" type="number" min="1" value="{{ $numero }}" required>
            </div>
            <div class="form-acts">
                <button class="btn btn-ghost btn-sm">Consultar</button>
            </div>
        </form>

        @isset($consulta)
            <x-arca-resultado :respuesta="$consulta" />
        @endisset
    </div>

    <div style="height:60px"></div>
@endsection
