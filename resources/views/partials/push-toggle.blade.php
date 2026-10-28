{{-- Activar o desactivar los avisos en este dispositivo.

     Es el único pedazo de JavaScript del panel que no degrada: sin él no hay
     notificaciones, y no existe forma de suscribirse con HTML. Lo que sí hace
     es no romper nada —quien no lo tenga sigue viendo los avisos en la
     campana, igual que siempre—, así que el bloque arranca oculto y solo
     aparece si el navegador puede.

     La suscripción es por dispositivo: el mismo usuario puede tener el
     teléfono activado y la computadora no. --}}
<div class="card push-caja" hidden data-push>
    <h2 class="push-titulo">Avisos en este dispositivo</h2>
    <p class="hint" data-push-texto></p>
    <button class="btn btn-ghost btn-sm" type="button" data-push-boton hidden></button>
    <p class="hint" data-push-ayuda hidden></p>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
    (function () {
        var caja = document.querySelector('[data-push]');
        if (!caja || !window.ghPush) return;

        var boton = caja.querySelector('[data-push-boton]');
        var texto = caja.querySelector('[data-push-texto]');
        var ayuda = caja.querySelector('[data-push-ayuda]');

        function decir(mensaje, sugerencia) {
            texto.textContent = mensaje;
            ayuda.textContent = sugerencia || '';
            ayuda.hidden      = !sugerencia;
        }

        // En iPhone sin instalar no hay nada que ofrecer, pero callarse deja a
        // esa persona buscando un botón que no existe. Se explica el paso que
        // falta, que no es algo que el panel pueda hacer por ella.
        if (!window.ghPush.soportado()) {
            if (window.ghPush.esIphoneSinInstalar()) {
                caja.hidden = false;
                decir(
                    'Para recibir avisos en el iPhone hay que agregar el panel a la pantalla de inicio.',
                    'Tocá Compartir y elegí «Agregar a pantalla de inicio». Entrando desde ese ícono te va a aparecer el botón para activarlos.'
                );
            }

            return;
        }

        caja.hidden = false;

        function pintar(estado) {
            if (estado === 'bloqueado') {
                boton.hidden = true;
                decir(
                    'Los bloqueaste para este sitio.',
                    'Para volver a activarlos hay que permitir las notificaciones desde la configuración del navegador: el panel no puede volver a preguntar.'
                );

                return;
            }

            var activo = estado === 'activo';

            boton.hidden      = false;
            boton.textContent = activo ? 'Desactivar' : 'Activar';
            decir(activo
                ? 'Están activados. Vas a recibir un aviso cuando te asignen un proyecto o una tarea.'
                : 'Recibí un aviso en la pantalla aunque no tengas el panel abierto.');
        }

        window.ghPush.estado().then(pintar);

        boton.addEventListener('click', function () {
            boton.disabled = true;

            window.ghPush.estado()
                .then(function (estado) {
                    return estado === 'activo' ? window.ghPush.desactivar() : window.ghPush.activar();
                })
                .then(pintar)
                .catch(function () { decir('No se pudo cambiar la configuración. Probá de nuevo.'); })
                .finally(function () { boton.disabled = false; });
        });
    })();
</script>
