{{-- Se ofrece una sola vez por dispositivo instalar el panel como app.

     Va temprano en el <body> a proposito: el evento beforeinstallprompt lo
     dispara el navegador apenas decide que el sitio es instalable, y si nadie
     lo esta escuchando en ese momento se pierde.

     Android y escritorio tienen ese evento, asi que se puede ofrecer un boton
     de verdad. iPhone no lo tiene —Apple nunca lo implemento—, y la unica
     forma de instalar es Compartir > Agregar a pantalla de inicio. Ahi el
     dialogo explica el paso en vez de mentir con un boton que no hace nada. --}}
<dialog class="ofrecer" data-instalar>
    <h2 class="ofrecer-titulo">¿Desea instalar la app?</h2>
    <p class="ofrecer-texto" data-instalar-texto>
        Instale la aplicacion y acceda desde su movil.
    </p>
    <p class="hint" data-instalar-pasos hidden></p>

    <div class="ofrecer-acts">
        <button class="btn btn-ghost btn-sm" type="button" data-instalar-no>Ahora no</button>
        <button class="btn btn-sm" type="button" data-instalar-si hidden>Instalar</button>
    </div>
</dialog>

<script nonce="{{ $cspNonce ?? '' }}">
    /*
     * Un solo diálogo por carga de página. Instalar y notificaciones podrían
     * querer aparecer a la vez, y dos carteles encimados al entrar son una
     * forma segura de que la gente cierre los dos sin leer. El que no alcanza
     * a mostrarse no se marca como ofrecido, así que aparece la próxima vez.
     */
    window.ghDialogo = {
        libre: true,
        tomar: function () {
            if (! this.libre) return false;

            this.libre = false;

            return true;
        },
    };

    (function () {
        var dialogo = document.querySelector('[data-instalar]');
        var memoria = 'goharv.instalar.ofrecido';

        if (! dialogo || typeof dialogo.showModal !== 'function') return;

        function yaInstalada() {
            return window.navigator.standalone === true
                || window.matchMedia('(display-mode: standalone)').matches;
        }

        function esIphone() {
            return /iPad|iPhone|iPod/.test(navigator.userAgent)
                || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        }

        function recordar() {
            try { localStorage.setItem(memoria, '1'); } catch (e) {}
        }

        function yaSeOfrecio() {
            try { return !! localStorage.getItem(memoria); } catch (e) { return true; }
        }

        if (yaInstalada() || yaSeOfrecio()) return;

        // Si la instalan por el menú del navegador, no hay nada que ofrecer.
        window.addEventListener('appinstalled', recordar);

        function cerrar() {
            recordar();
            dialogo.close();
        }

        function abrir() {
            if (! window.ghDialogo.tomar()) return false;

            dialogo.showModal();
            dialogo.querySelector('[data-instalar-no]').addEventListener('click', cerrar);
            dialogo.addEventListener('cancel', cerrar);

            return true;
        }

        if (esIphone()) {
            // Sin evento que esperar: se ofrece ahora o nunca.
            dialogo.querySelector('[data-instalar-pasos]').textContent =
                'Tocá el botón Compartir y elegí «Agregar a pantalla de inicio».';
            dialogo.querySelector('[data-instalar-pasos]').hidden = false;
            dialogo.querySelector('[data-instalar-no]').textContent = 'Entendido';

            abrir();

            return;
        }

        window.addEventListener('beforeinstallprompt', function (e) {
            // Frena el cartel propio del navegador para mostrar el nuestro,
            // que explica para qué sirve antes de pedir nada.
            e.preventDefault();

            if (! abrir()) return;

            var boton = dialogo.querySelector('[data-instalar-si]');

            boton.hidden = false;
            boton.addEventListener('click', function () {
                boton.disabled = true;

                // prompt() solo se puede llamar una vez por evento, y tiene
                // que salir de un clic.
                e.prompt();
                e.userChoice.finally(cerrar);
            });
        });
    })();
</script>
