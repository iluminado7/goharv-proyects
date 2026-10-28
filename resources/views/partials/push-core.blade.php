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

        function avisarAlPanel(url, metodo, cuerpo) {
            var token = document.querySelector('meta[name=csrf-token]');

            return fetch(url, {
                method: metodo,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token ? token.content : '',
                },
                credentials: 'same-origin',
                body: JSON.stringify(cuerpo),
            });
        }

        /** 'bloqueado' | 'activo' | 'inactivo' */
        function estado() {
            if (Notification.permission === 'denied') return Promise.resolve('bloqueado');

            return navigator.serviceWorker.ready
                .then(function (registro) { return registro.pushManager.getSubscription(); })
                .then(function (suscripcion) { return suscripcion ? 'activo' : 'inactivo'; });
        }

        /**
         * Pide el permiso y suscribe. Tiene que llamarse desde un clic: si se
         * pide sin que el usuario haya tocado nada, el navegador lo bloquea
         * solo y después ya no se puede volver a preguntar.
         */
        function activar() {
            return Notification.requestPermission().then(function (permiso) {
                if (permiso !== 'granted') return permiso === 'denied' ? 'bloqueado' : 'inactivo';

                return navigator.serviceWorker.ready.then(function (registro) {
                    return registro.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: aBytes(clave),
                    });
                }).then(function (nueva) {
                    return avisarAlPanel(rutas.alta, 'POST', {
                        endpoint: nueva.endpoint,
                        keys: nueva.toJSON().keys,
                    });
                }).then(function () { return 'activo'; });
            });
        }

        function desactivar() {
            return navigator.serviceWorker.ready
                .then(function (registro) { return registro.pushManager.getSubscription(); })
                .then(function (suscripcion) {
                    if (!suscripcion) return 'inactivo';

                    var endpoint = suscripcion.endpoint;

                    return suscripcion.unsubscribe()
                        .then(function () { return avisarAlPanel(rutas.baja, 'DELETE', {endpoint: endpoint}); })
                        .then(function () { return 'inactivo'; });
                });
        }

        return {
            soportado: soportado,
            esIphoneSinInstalar: esIphoneSinInstalar,
            estado: estado,
            activar: activar,
            desactivar: desactivar,
        };
    })();
</script>
