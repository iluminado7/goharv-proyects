{{-- Lo que saben en común el botón de Mi perfil y el ofrecimiento de la
     primera vez: cómo está la cosa en este dispositivo, cómo suscribirse y
     cómo darse de baja.

     Va temprano en el <body> porque los dos scripts que lo usan vienen
     después y se ejecutan en orden. --}}
<script nonce="{{ $cspNonce ?? '' }}">
    window.ghPush = (function () {
        var clave = @json(config('webpush.vapid.public_key'));
        var rutas = { alta: @json(route('push.store')), baja: @json(route('push.destroy')) };

        function soportado() {
            return 'serviceWorker' in navigator && 'PushManager' in window && !!clave;
        }

        // En iPhone el PushManager solo existe si la app está instalada en la
        // pantalla de inicio. Sirve para explicarlo en vez de no mostrar nada.
        function esIphoneSinInstalar() {
            var esIOS = /iPad|iPhone|iPod/.test(navigator.userAgent)
                || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            var instalada = window.navigator.standalone === true
                || window.matchMedia('(display-mode: standalone)').matches;

            return esIOS && !instalada;
        }

        // La clave viaja en base64url y PushManager la quiere en bytes.
        function aBytes(base64url) {
            var base64 = (base64url + '='.repeat((4 - base64url.length % 4) % 4))
                .replace(/-/g, '+').replace(/_/g, '/');
            var crudo  = atob(base64);
            var bytes  = new Uint8Array(crudo.length);

            for (var i = 0; i < crudo.length; i++) bytes[i] = crudo.charCodeAt(i);

            return bytes;
        }

        /**
         * fetch() solo rechaza si no hubo red: un 500 o un 422 le parecen
         * respuestas perfectamente validas. Sin este chequeo el panel podia
         * contestar que no y el boton igual decia «activados» — que es
         * exactamente lo que paso cuando faltaba la tabla de suscripciones.
         */
        function avisarAlPanel(url, metodo, cuerpo) {
            var token = document.querySelector('meta[name=csrf-token]');

            return fetch(url, {
                method: metodo,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token ? token.content : '',
                },
                credentials: 'same-origin',
                body: JSON.stringify(cuerpo),
            }).then(function (respuesta) {
                if (! respuesta.ok) throw new Error('el panel respondio ' + respuesta.status);

                return respuesta;
            });
        }

        /**
         * serviceWorker.ready no resuelve nunca si el service worker no llego
         * a registrarse: no falla, se queda esperando para siempre. Sin este
         * limite la tarjeta de Mi perfil quedaba visible pero vacia y sin
         * boton, sin un solo error en consola.
         */
        function registroListo() {
            return Promise.race([
                navigator.serviceWorker.ready,
                new Promise(function (_, rechazar) {
                    setTimeout(function () { rechazar(new Error('sin service worker')); }, 5000);
                }),
            ]);
        }

        /** 'bloqueado' | 'activo' | 'inactivo' | 'sin-soporte' */
        function estado() {
            if (Notification.permission === 'denied') return Promise.resolve('bloqueado');

            return registroListo()
                .then(function (registro) { return registro.pushManager.getSubscription(); })
                .then(function (suscripcion) { return suscripcion ? 'activo' : 'inactivo'; })
                .catch(function () { return 'sin-soporte'; });
        }

        /**
         * Pide el permiso y suscribe. Tiene que llamarse desde un clic: si se
         * pide sin que el usuario haya tocado nada, el navegador lo bloquea
         * solo y después ya no se puede volver a preguntar.
         */
        function activar() {
            return Notification.requestPermission().then(function (permiso) {
                if (permiso !== 'granted') return avisarCambio(permiso === 'denied' ? 'bloqueado' : 'inactivo');

                return registroListo().then(function (registro) {
                    return registro.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: aBytes(clave),
                    });
                }).then(function (nueva) {
                    return avisarAlPanel(rutas.alta, 'POST', {
                        endpoint: nueva.endpoint,
                        keys: nueva.toJSON().keys,
                    }).then(function () {
                        return avisarCambio('activo');
                    }).catch(function (e) {
                        // El navegador quedo suscripto pero el panel no se
                        // entero. Sin deshacerlo, al recargar diria que estan
                        // activadas y no llegaria nunca nada.
                        return nueva.unsubscribe().then(function () { throw e; });
                    });
                });
            });
        }

        function desactivar() {
            return registroListo()
                .then(function (registro) { return registro.pushManager.getSubscription(); })
                .then(function (suscripcion) {
                    if (!suscripcion) return avisarCambio('inactivo');

                    var endpoint = suscripcion.endpoint;

                    return suscripcion.unsubscribe()
                        .then(function () { return avisarAlPanel(rutas.baja, 'DELETE', {endpoint: endpoint}); })
                        .then(function () { return avisarCambio('inactivo'); });
                });
        }

        /*
         * En Mi perfil conviven el boton y el ofrecimiento de la primera vez,
         * y los dos cambian lo mismo. Sin esto, activar desde el dialogo
         * dejaba al boton diciendo «Activar» sobre algo ya activo: tocarlo lo
         * apagaba, y parecia que hacia lo contrario de lo que decia.
         */
        function avisarCambio(estado) {
            document.dispatchEvent(new CustomEvent('gh:push', { detail: estado }));

            return estado;
        }

        return {
            soportado: soportado,
            alCambiar: function (cb) { document.addEventListener('gh:push', function (e) { cb(e.detail); }); },
            esIphoneSinInstalar: esIphoneSinInstalar,
            estado: estado,
            activar: activar,
            desactivar: desactivar,
        };
    })();
</script>
