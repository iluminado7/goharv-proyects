{{-- Se ofrece una sola vez por dispositivo, la primera vez que alguien entra
     después de que existan las notificaciones.

     El permiso real del navegador NO se pide acá: se pide recién cuando tocan
     «Activar». Si se pidiera al cargar la página, Chrome y Firefox lo bloquean
     solos por no venir de un gesto del usuario, y después no hay forma de
     volver a preguntar. Este diálogo es el que se puede repetir; el del
     navegador, no.

     Que ya se ofreció se guarda en localStorage y no en la base: la
     suscripción es por dispositivo, así que decir «ahora no» en el teléfono no
     tiene por qué callar el ofrecimiento en la computadora. --}}
<dialog class="ofrecer" data-push-invite>
    <h2 class="ofrecer-titulo">¿Desea activar notificaciones?</h2>
    <p class="ofrecer-texto">
        Te avisamos cuando te asignen un proyecto o una tarea.
    </p>
    <p class="hint">Lo podés cambiar cuando quieras desde <em>Mi perfil</em>.</p>

    <div class="ofrecer-acts">
        <button class="btn btn-ghost btn-sm" type="button" data-push-no>Ahora no</button>
        <button class="btn btn-sm" type="button" data-push-si>Activar</button>
    </div>
</dialog>

<script nonce="{{ $cspNonce ?? '' }}">
    (function () {
        var dialogo = document.querySelector('[data-push-invite]');
        var memoria = 'goharv.push.ofrecido';

        if (!dialogo || !window.ghPush || !window.ghPush.soportado()) return;
        if (typeof dialogo.showModal !== 'function') return;

        // Si se limpian los datos del navegador vuelve a ofrecerse una vez.
        // Es el precio de no guardarlo en la base, y es barato.
        try {
            if (localStorage.getItem(memoria)) return;
        } catch (e) {
            return;
        }

        function recordar() {
            try { localStorage.setItem(memoria, '1'); } catch (e) {}
        }

        function cerrar() {
            recordar();
            dialogo.close();
        }

        window.ghPush.estado().then(function (estado) {
            // A quien ya los tiene, o los bloqueó, no hay nada que ofrecerle.
            if (estado !== 'inactivo') {
                recordar();

                return;
            }

            dialogo.showModal();

            dialogo.querySelector('[data-push-no]').addEventListener('click', cerrar);

            dialogo.querySelector('[data-push-si]').addEventListener('click', function (e) {
                e.target.disabled = true;

                // Desde el clic: es lo que hace que el navegador acepte pedir
                // el permiso en vez de bloquearlo de entrada.
                window.ghPush.activar().finally(cerrar);
            });

            // Escape cuenta como «ahora no»: no se vuelve a preguntar.
            dialogo.addEventListener('cancel', cerrar);
        });
    })();
</script>
