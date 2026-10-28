{{-- La campana y la barra lateral del celular abren y cierran sin JavaScript:
     una con <details> y la otra con un checkbox. Esto agrega lo unico que
     ninguno de los dos trae de fabrica, cerrarse al tocar afuera o con
     Escape. Sin el script los dos siguen funcionando: se cierran volviendo a
     tocar su boton. --}}
<script nonce="{{ $cspNonce ?? '' }}">
    (function () {
        var campana = document.querySelector('.campana-caja');
        var menu    = document.getElementById('abrir-menu');
        var barra   = document.querySelector('.barra');
        var abridor = document.querySelector('.hamburguesa');
        var telon   = document.querySelector('.barra-telon');

        /**
         * Que cuenta como "adentro del menu" y por lo tanto no lo cierra.
         *
         * El checkbox va en la lista aunque no se vea. Tocar un <label>
         * dispara dos clics: el del label y otro que arma el navegador sobre
         * el <input>, y ese segundo tambien burbujea hasta aca. Como el input
         * es hermano del topbar y no esta adentro de la barra, sin esta linea
         * el menu se abria y se cerraba en el mismo toque.
         *
         * La hamburguesa y el telon tambien entran, por lo mismo al reves:
         * los dos son labels del checkbox, asi que cerrar es trabajo de ellos.
         * Si ademas lo desmarcaramos desde aca, la activacion del label lo
         * volveria a marcar y quedaria abierto.
         */
        function enElMenu(nodo) {
            return nodo === menu
                || (barra && barra.contains(nodo))
                || (abridor && abridor.contains(nodo))
                || (telon && telon.contains(nodo));
        }

        function cerrarTodo() {
            if (campana) campana.open = false;
            if (menu) menu.checked = false;
        }

        document.addEventListener('click', function (e) {
            if (campana && campana.open && ! campana.contains(e.target)) {
                campana.open = false;
            }

            if (menu && menu.checked && ! enElMenu(e.target)) {
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
