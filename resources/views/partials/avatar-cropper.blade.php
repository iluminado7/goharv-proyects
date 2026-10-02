{{-- Tercera excepcion a "Blade sin JavaScript", y la mas grande de las tres.
     Encuadrar una foto es inherentemente interactivo: hay que verla, moverla y
     hacer zoom antes de decidir. Nada de eso se puede hacer desde el servidor.

     Va como mejora progresiva: sin JS el formulario sube el archivo tal cual y
     el servidor recorta al centro, igual que antes. Con JS, el recorte lo
     decide quien sube la foto. El servidor sigue recodificando lo que llega:
     lo que manda el navegador no se confia mas que cualquier otra subida. --}}
<script nonce="{{ $cspNonce ?? '' }}">
    (function () {
        var input = document.getElementById('avatar');

        if (!input || typeof HTMLCanvasElement === 'undefined' || !window.FileReader) {
            return;
        }

        var LADO = 320;   // Lado del recuadro en pantalla.
        var SALIDA = 512; // Lado del JPEG que se manda; el servidor lo baja a 256.

        var form = input.form;
        var listo = false; // true cuando el archivo del input ya es el recortado.

        // --- El cuadro, armado desde el script: sin JS no queda nada muerto ---
        var fondo = document.createElement('div');
        fondo.className = 'recorte-fondo';
        fondo.hidden = true;
        fondo.innerHTML =
            '<div class="recorte-caja" role="dialog" aria-modal="true" aria-label="Encuadrá tu foto">' +
              '<h2>Encuadrá tu foto</h2>' +
              '<p class="hint">Arrastrá la imagen para mover. Lo que quede dentro del círculo es tu foto.</p>' +
              '<div class="recorte-lienzo"><canvas width="' + LADO + '" height="' + LADO + '"></canvas></div>' +
              '<div class="recorte-zoom">' +
                '<button type="button" class="btn btn-ghost btn-sm" data-mas>Acercar +</button>' +
                '<button type="button" class="btn btn-ghost btn-sm" data-menos>Alejar −</button>' +
              '</div>' +
              '<div class="recorte-acts">' +
                '<button type="button" class="btn btn-ghost btn-sm" data-cancelar>Cancelar</button>' +
                '<button type="button" class="btn btn-sm" data-aplicar>Aplicar y subir</button>' +
              '</div>' +
            '</div>';

        document.body.appendChild(fondo);

        var canvas = fondo.querySelector('canvas');
        var ctx = canvas.getContext('2d');
        var imagen = null;
        var escala = 1;
        var minEscala = 1;
        var x = 0;
        var y = 0;

        function limitar() {
            escala = Math.min(Math.max(escala, minEscala), minEscala * 6);

            var ancho = imagen.width * escala;
            var alto = imagen.height * escala;

            // La imagen siempre tapa el recuadro: nunca se ve un hueco.
            x = Math.min(0, Math.max(x, LADO - ancho));
            y = Math.min(0, Math.max(y, LADO - alto));
        }

        function dibujar() {
            limitar();

            ctx.clearRect(0, 0, LADO, LADO);
            ctx.drawImage(imagen, x, y, imagen.width * escala, imagen.height * escala);

            // Oscurece todo y despues "borra" el circulo, que queda nitido.
            ctx.save();
            ctx.fillStyle = 'rgba(0,0,0,.5)';
            ctx.fillRect(0, 0, LADO, LADO);
            ctx.globalCompositeOperation = 'destination-out';
            ctx.beginPath();
            ctx.arc(LADO / 2, LADO / 2, LADO / 2, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();

            ctx.save();
            ctx.strokeStyle = 'rgba(255,255,255,.85)';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.arc(LADO / 2, LADO / 2, LADO / 2 - 0.5, 0, Math.PI * 2);
            ctx.stroke();

            // Guias de tercios, como en cualquier recortador.
            ctx.strokeStyle = 'rgba(255,255,255,.35)';
            ctx.setLineDash([4, 4]);
            [1, 2].forEach(function (i) {
                var p = (LADO / 3) * i;
                ctx.beginPath(); ctx.moveTo(p, 0); ctx.lineTo(p, LADO); ctx.stroke();
                ctx.beginPath(); ctx.moveTo(0, p); ctx.lineTo(LADO, p); ctx.stroke();
            });
            ctx.restore();
        }

        function zoom(factor) {
            var antes = escala;
            escala *= factor;
            limitar();

            // Hace zoom hacia el centro y no hacia la esquina.
            var centro = LADO / 2;
            x = centro - (centro - x) * (escala / antes);
            y = centro - (centro - y) * (escala / antes);

            dibujar();
        }

        function abrir(archivo) {
            var lector = new FileReader();

            lector.onload = function (e) {
                var img = new Image();

                img.onload = function () {
                    imagen = img;
                    minEscala = Math.max(LADO / img.width, LADO / img.height);
                    escala = minEscala;
                    x = (LADO - img.width * escala) / 2;
                    y = (LADO - img.height * escala) / 2;

                    fondo.hidden = false;
                    document.body.classList.add('sin-scroll');
                    dibujar();
                    fondo.querySelector('[data-aplicar]').focus();
                };

                img.onerror = cerrar; // Si no se puede leer, que decida el servidor.
                img.src = e.target.result;
            };

            lector.readAsDataURL(archivo);
        }

        function cerrar() {
            fondo.hidden = true;
            document.body.classList.remove('sin-scroll');
            imagen = null;
        }

        // --- Arrastrar ---
        var arrastrando = false;
        var desdeX = 0;
        var desdeY = 0;

        canvas.addEventListener('pointerdown', function (e) {
            if (!imagen) return;
            arrastrando = true;
            desdeX = e.clientX - x;
            desdeY = e.clientY - y;
            canvas.setPointerCapture(e.pointerId);
        });

        canvas.addEventListener('pointermove', function (e) {
            if (!arrastrando) return;
            x = e.clientX - desdeX;
            y = e.clientY - desdeY;
            dibujar();
        });

        ['pointerup', 'pointercancel'].forEach(function (evento) {
            canvas.addEventListener(evento, function () { arrastrando = false; });
        });

        fondo.querySelector('[data-mas]').addEventListener('click', function () { zoom(1.25); });
        fondo.querySelector('[data-menos]').addEventListener('click', function () { zoom(1 / 1.25); });

        fondo.querySelector('[data-cancelar]').addEventListener('click', function () {
            input.value = '';
            cerrar();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !fondo.hidden) {
                input.value = '';
                cerrar();
            }
        });

        fondo.querySelector('[data-aplicar]').addEventListener('click', function () {
            var salida = document.createElement('canvas');
            salida.width = SALIDA;
            salida.height = SALIDA;

            var k = SALIDA / LADO;
            var sctx = salida.getContext('2d');
            sctx.fillStyle = '#fff';
            sctx.fillRect(0, 0, SALIDA, SALIDA);
            sctx.drawImage(imagen, x * k, y * k, imagen.width * escala * k, imagen.height * escala * k);

            salida.toBlob(function (blob) {
                // Si el navegador no deja reemplazar el archivo del input, se
                // sube el original y el servidor recorta al centro: peor
                // encuadre, pero nunca un error en la cara del usuario.
                try {
                    var dt = new DataTransfer();
                    dt.items.add(new File([blob], 'perfil.jpg', { type: 'image/jpeg' }));
                    input.files = dt.files;
                } catch (err) { /* se sube el original */ }

                listo = true;
                cerrar();
                form.submit();
            }, 'image/jpeg', 0.9);
        });

        input.addEventListener('change', function () {
            if (!listo && input.files && input.files[0]) {
                abrir(input.files[0]);
            }
        });

        // Si alguien manda el formulario sin pasar por el recorte (Enter en el
        // campo, por ejemplo), se abre el cuadro en vez de subir a ciegas.
        form.addEventListener('submit', function (e) {
            if (!listo && input.files && input.files[0]) {
                e.preventDefault();
                abrir(input.files[0]);
            }
        });
    })();
</script>
