{{-- El fondo y el plegado de la barra son preferencias: viven en una cookie
     que escribe el servidor. Mandarlas por formulario funciona, pero recargar
     la pagina entera para mover un atributo se nota y molesta.

     Los dos formularios siguen siendo formularios de verdad —sin JS andan
     igual, con su viaje al servidor—. Lo que agrega esto es aplicar el cambio
     en el acto y mandar el mismo POST por atras, solo para que la proxima
     pagina llegue como quedo. Si ese POST falla no hay nada que avisar: lo
     unico que se pierde es que la preferencia no sobreviva a la navegacion. --}}
<script nonce="{{ $cspNonce ?? '' }}">
    (function () {
        function alVuelo(form, aplicar) {
            if (!form) return;

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                aplicar(form.querySelector('button'));

                fetch(form.action, {
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': form.querySelector('[name=_token]').value},
                    body: new FormData(form),
                    credentials: 'same-origin',
                    keepalive: true,
                }).catch(function () {});
            });
        }

        function rotular(boton, texto) {
            boton.setAttribute('aria-label', texto);
            boton.setAttribute('title', texto);
        }

        alVuelo(document.querySelector('.barra-plegar'), function (boton) {
            var angosta = document.body.classList.toggle('barra-angosta');

            boton.setAttribute('aria-expanded', angosta ? 'false' : 'true');
            rotular(boton, angosta ? 'Ampliar el menú' : 'Plegar el menú');
        });

        alVuelo(document.querySelector('[data-tema]'), function (boton) {
            var claro = document.documentElement.dataset.theme !== 'claro';

            document.documentElement.dataset.theme = claro ? 'claro' : 'oscuro';
            rotular(boton, claro ? 'Pasar a fondo negro' : 'Pasar a fondo claro');

            // La barra del navegador en el celular tambien sigue al tema.
            var barra = document.querySelector('meta[name=theme-color]');
            if (barra) barra.content = claro ? '#FFFFFF' : '#0B0B0B';
        });
    })();
</script>
