{{-- Unico JavaScript del panel. Son seis lineas y no se puede evitar: un
     service worker se registra desde el navegador o no existe. Si falla, la
     app sigue andando como siempre; lo unico que se pierde es poder
     instalarla y la pantalla de sin conexion. --}}
<script nonce="{{ $cspNonce ?? '' }}">
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(function () {});
        });
    }
</script>
