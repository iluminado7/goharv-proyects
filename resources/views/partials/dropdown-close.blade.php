{{-- La campana y el menu del celular abren y cierran sin JavaScript: una con
     <details> y el otro con un checkbox. Esto agrega lo unico que ninguno de
     los dos trae de fabrica, cerrarse al tocar afuera o con Escape. Sin el
     script los dos siguen funcionando: se cierran volviendo a tocar su boton. --}}
<script nonce="{{ $cspNonce ?? '' }}">
    (function () {
        var campana = document.querySelector('.campana-caja');
        var menu    = document.getElementById('abrir-menu');
        var navZona = document.querySelector('.mast-nav');

        function cerrarTodo() {
            if (campana) campana.open = false;
            if (menu) menu.checked = false;
        }

        document.addEventListener('click', function (e) {
            var enCampana = campana && campana.contains(e.target);
            var enMenu    = navZona && navZona.contains(e.target);

            if (campana && campana.open && !enCampana) {
                campana.open = false;
            }

            if (menu && menu.checked && !enMenu) {
                menu.checked = false;
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                cerrarTodo();
            }
        });
    })();
</script>
