# Integración ARCA (modo desarrollo con Afip SDK)

## 1. Instalar

```bash
composer require afipsdk/afip.php
```

Copiá los archivos:

- `config/arca.php`
- `app/Services/ArcaService.php`
- `app/Http/Controllers/ArcaTestController.php`

## 2. `.env`

```env
ARCA_PRODUCTION=false
ARCA_CUIT=20409378472
ARCA_PTO_VTA=1
ARCA_ACCESS_TOKEN=tu_token_de_app.afipsdk.com
ARCA_CERT_PATH=
ARCA_KEY_PATH=
```

Después: `php artisan config:clear`

## 3. Rutas

Ya están en `routes/web.php`, **adentro del grupo `auth` + `admin`**, junto a
Actividad. No hace falta agregar nada.

```php
if (app()->environment('local', 'testing')) {
    Route::prefix('arca')->controller(ArcaTestController::class)->group(function () {
        Route::get('/', 'pantalla')->name('arca.test');
        Route::post('/emitir', 'emitir')->name('arca.test.emitir');
        Route::get('/estado', 'estado')->name('arca.estado');
        Route::get('/conexion', 'conexion')->name('arca.conexion');
    });
}
```

Hacen falta **tres** cosas para entrar: sesión abierta, rol de responsable y
estar fuera de producción. Antes la única traba era el entorno, y eso era un
hallazgo de la auditoría (SEG-03): aunque sea una pantalla de prueba, emite
comprobantes, y con `ARCA_PRODUCTION=true` son fiscales y reales.

`ArcaService` tiene además una cuarta traba que no depende de rutas: se niega a
construirse si `ARCA_PRODUCTION` está en true fuera de producción.

## 4. Probar

Entrá con una cuenta de responsable y abrí **Facturación** en el menú lateral.
La pestaña solo aparece si la ruta existe, así que en producción no se ve.

La pantalla tiene tres bloques: probar conexión, emitir y consultar. Cada uno
muestra la respuesta de ARCA tal cual.

> Los `curl` que traía esta guía ya no sirven, y es a propósito: las rutas
> piden sesión, así que no hay forma de pegarles desde la terminal sin una
> cookie. El `POST` tampoco tiene ya la excepción de CSRF que se le había
> puesto para eso. Para mirar la respuesta cruda están `/arca/estado` y
> `/arca/conexion`, que contestan JSON desde el navegador ya logueado.

## 5. Pasar a tu certificado

1. Generás el cert en WSASS (homo) o en Administración de Certificados Digitales (prod).
2. Guardás `.crt` y `.key` en `storage/app/arca/` (y agregás esa carpeta al `.gitignore`).
3. Cambiás en el `.env`:

```env
ARCA_CUIT=tu_cuit_o_el_de_goharv
ARCA_PTO_VTA=el_punto_de_venta_web_services
ARCA_CERT_PATH=arca/cert.crt
ARCA_KEY_PATH=arca/private.key
```

4. `ARCA_PRODUCTION=true` solo cuando salgas a producción.

El código no se toca.

---

## Lo que salió al probarlo (29/10/2026)

Los cuatro pasos funcionan contra homologación con el CUIT de prueba
compartido. Factura emitida: **16497**, CAE `86441030270826`, resultado `A`.

Tres cosas que no estaban en la guía y costaron encontrar:

### El número de comprobante es una carrera

No lo elegís vos: hay que preguntarle a ARCA cuál fue el último autorizado y
pedir el siguiente. Entre esas dos llamadas puede colarse otro, y ARCA
contesta **10016**. `CreateNextVoucher` del SDK no ayuda: hace lo mismo y
tampoco reintenta.

`ArcaService::autorizar()` reintenta hasta cuatro veces, solo ante el 10016 y
con espera creciente. **Esto se queda en producción**: con un CUIT propio pasa
igual el día que dos personas del equipo facturen al mismo tiempo.

### El 10016 también puede ser la fecha

El mensaje dice "el número *o fecha*". Los comprobantes van en orden de fecha
no decreciente, así que no se puede emitir con una fecha anterior a la del
último autorizado.

En el sandbox compartido alguien emitió con fecha futura (`20261103`), y hasta
que el calendario la alcance nadie puede facturar con la fecha de hoy. Por eso
`emitirFacturaC()` acepta una `$fecha` opcional. **Por omisión usa hoy, que es
lo correcto**; el parámetro existe para la ventana que ARCA permite (±5 días
productos, ±10 servicios) y para poder probar en el sandbox.

### La zona horaria importa

La app corre en UTC. En Argentina eso adelanta el día tres horas antes de
medianoche, así que una factura emitida a las 21:30 se fecharía al día
siguiente. `ArcaService` usa `America/Argentina/Buenos_Aires` explícito.

## Pendiente antes de producción

- **El certificado no puede vivir en `storage/app`.** Laravel Cloud borra el
  disco en cada deploy y en cada arranque tras el scale-to-zero. Va como
  variable de entorno o en un bucket. `ArcaService` ya lee el *contenido* y no
  la ruta, así que el cambio es de configuración y no de código.
- **Los PDFs de Afip SDK vencen a las 24 h.** Si el panel emite comprobantes,
  hay que bajar el PDF y guardarlo, no enlazar al de ellos.
- **Las peticiones pasan por `app.afipsdk.com`**, no van directo a ARCA. Para
  facturar de verdad, decisión a tomar a conciencia.
- ~~Las rutas de `/arca/test` están fuera de `auth`~~ — corregido el
  2026-11-03: viven adentro de `auth` + `admin`, con el entorno como tercera
  traba y una cuarta en `ArcaService`. Cuando facturar deje de ser
  exploración, se saca el `if` del entorno y la pantalla sale sin tocar nada
  más.
