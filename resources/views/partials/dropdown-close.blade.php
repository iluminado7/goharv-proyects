{{-- Cuarta y mas chica de las mejoras con JavaScript. El desplegable abre y
     cierra solo con <details>, que es HTML puro; esto agrega lo unico que el
     elemento no trae: cerrarlo al tocar afuera o con Escape. Sin el script el
     cuadro sigue funcionando, se cierra volviendo a tocar la campana. --}}
<script nonce="{{ $cspNonce ?? '' }}">
    (function () {
        var caja = document.querySelector('.campana-caja');

        if (!caja) {
            return;
        }

        document.addEventListener('click', function (e) {
            if (caja.open && !caja.contains(e.target)) {
                caja.open = false;
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                caja.open = false;
            }
        });
    })();
</script>
