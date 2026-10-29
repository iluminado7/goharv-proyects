{{-- Lo que contestó ARCA, crudo. En una pantalla de exploración el valor
     está en ver la respuesta tal cual: cualquier resumen esconde justo el
     campo que uno no sabía que necesitaba mirar. --}}
@props(['respuesta'])

<div class="arca-salida {{ $respuesta['ok'] ? 'bien' : 'mal' }}">
    <p class="arca-titulo">{{ $respuesta['ok'] ? 'Respondió' : 'Falló' }}</p>

    <pre>{{ $respuesta['ok']
        ? json_encode($respuesta['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : $respuesta['error'] }}</pre>
</div>
