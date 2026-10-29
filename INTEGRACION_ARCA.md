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

## 3. Rutas (agregar al final de `routes/web.php`)

```php
use App\Http\Controllers\ArcaTestController;

if (app()->environment('local')) {
    Route::prefix('arca/test')->controller(ArcaTestController::class)->group(function () {
        Route::get('estado', 'estado');
        Route::get('conexion', 'conexion');
        Route::post('factura', 'factura')->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        Route::get('factura/{numero}', 'consultar');
    });
}
```

## 4. Probar

```bash
# Estado de servidores
curl http://localhost:8000/arca/test/estado

# Conexión completa (auth + punto de venta)
curl http://localhost:8000/arca/test/conexion

# Emitir Factura C de prueba a consumidor final
curl -X POST http://localhost:8000/arca/test/factura \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"importe": 1500}'

# Consultar la factura emitida
curl http://localhost:8000/arca/test/factura/1
```

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
