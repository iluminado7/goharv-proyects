{{-- Segunda y ultima excepcion a "Blade sin JavaScript": cambiar un campo de
     password a texto no se puede hacer desde el servidor ni con CSS. El boton
     lo crea el propio script, asi que si el JS no corre no queda un boton
     muerto en pantalla: el campo se comporta como siempre. --}}
<script nonce="{{ $cspNonce ?? '' }}">
    (function () {
        var OJO = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="2.6"/></svg>';
        var OJO_TACHADO = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9.6 5.7A9.7 9.7 0 0 1 12 5.5c6.4 0 10 6.5 10 6.5a17.6 17.6 0 0 1-3.4 4.2M6.3 7.8A17.4 17.4 0 0 0 2 12s3.6 6.5 10 6.5a9.9 9.9 0 0 0 3.9-.8"/><path d="M10.2 10.3a2.6 2.6 0 0 0 3.6 3.7"/><path d="M3 3l18 18"/></svg>';

        document.querySelectorAll('input[type="password"]').forEach(function (campo) {
            var envoltorio = document.createElement('div');
            envoltorio.className = 'con-ojo';

            campo.parentNode.insertBefore(envoltorio, campo);
            envoltorio.appendChild(campo);

            var boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'ojo';
            boton.innerHTML = OJO;
            boton.setAttribute('aria-label', 'Mostrar la clave');
            boton.setAttribute('aria-pressed', 'false');
            boton.setAttribute('title', 'Mostrar la clave');

            boton.addEventListener('click', function () {
                var visible = campo.type === 'text';

                campo.type = visible ? 'password' : 'text';
                boton.innerHTML = visible ? OJO : OJO_TACHADO;
                boton.setAttribute('aria-pressed', visible ? 'false' : 'true');
                boton.setAttribute('aria-label', visible ? 'Mostrar la clave' : 'Ocultar la clave');
                boton.setAttribute('title', boton.getAttribute('aria-label'));

                // Que no se pierda el cursor ni lo escrito al tocar el boton.
                campo.focus();
                campo.setSelectionRange(campo.value.length, campo.value.length);
            });

            envoltorio.appendChild(boton);
        });
    })();
</script>
