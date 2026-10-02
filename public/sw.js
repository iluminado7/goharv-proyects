/*
 * Service worker del panel GoHarv.
 *
 * Decision importante: las paginas NO se cachean. El panel muestra datos del
 * equipo y el telefono puede quedar prestado o perdido; un cache de HTML seria
 * una copia de los proyectos leible sin sesion. Solo se guardan los archivos
 * estaticos (los iconos) y una pantalla de "sin conexion".
 *
 * El CSS no necesita que se suba VERSION a mano: su URL lleva la fecha de
 * modificacion del archivo, asi que cada cambio es una direccion distinta.
 */

const VERSION = 'goharv-v2';

// Rutas relativas al alcance del service worker, para que funcione tanto en la
// raiz del dominio como colgando de una subcarpeta (XAMPP).
// El CSS no se precachea: su URL lleva la fecha del archivo, asi que cada
// cambio es una direccion nueva y no hay version vieja que pueda quedar
// pegada. Aca solo va lo que tiene URL fija.
const ESTATICOS = [
  'offline.html',
  'icons/icon-192.png',
  'icons/apple-touch-icon.png',
];

self.addEventListener('install', (evento) => {
  evento.waitUntil(
    caches
      .open(VERSION)
      .then((cache) => cache.addAll(ESTATICOS.map((ruta) => new URL(ruta, self.registration.scope).href)))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (evento) => {
  evento.waitUntil(
    caches
      .keys()
      .then((claves) => Promise.all(claves.filter((c) => c !== VERSION).map((c) => caches.delete(c))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (evento) => {
  const pedido = evento.request;

  // Nada de meterse con formularios ni con otros dominios.
  if (pedido.method !== 'GET' || new URL(pedido.url).origin !== self.location.origin) {
    return;
  }

  // Paginas: siempre de la red. Si no hay señal, la pantalla de sin conexion.
  if (pedido.mode === 'navigate') {
    evento.respondWith(
      fetch(pedido).catch(() => caches.match(new URL('offline.html', self.registration.scope).href))
    );
    return;
  }

  // Estaticos: del cache si esta, y de paso se refresca para la proxima.
  evento.respondWith(
    caches.match(pedido).then((guardado) => {
      const desdeRed = fetch(pedido)
        .then((respuesta) => {
          if (respuesta.ok) {
            const copia = respuesta.clone();
            caches.open(VERSION).then((cache) => cache.put(pedido, copia));
          }

          return respuesta;
        })
        .catch(() => guardado);

      return guardado || desdeRed;
    })
  );
});
