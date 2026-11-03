# Informe de seguridad — GoHarv Project Manager

**Fecha:** 2026-11-03 · **Laravel:** 12.70.2 · **PHP:** 8.2.12 · **Rama auditada:** `arca`
**Entorno:** local (XAMPP) + código desplegado en Laravel Cloud

**Alcance:** se revisó todo el código de la aplicación, las rutas, las policies,
la configuración versionada, el `.env` local y las dependencias.

**Lo que NO se revisó:** las variables de entorno de producción en Laravel Cloud
(no son accesibles desde acá), el servidor, los backups y el comando de deploy.
Dos de los hallazgos dependen de esa configuración y quedan marcados.

---

## Resumen ejecutivo

El panel está bastante mejor armado que el promedio: no hay inyección SQL, ni
XSS, ni escalada de privilegios, ni acceso a datos ajenos. Las policies cubren
todas las acciones y hay 357 tests que las sostienen.

Los dos problemas que importan son de **manejo de credenciales**, no de código:
un seeder que crea un administrador con una contraseña escrita en el repositorio
y que no está limitado a desarrollo, y las claves provisorias de las cuentas
nuevas que quedan escritas en texto plano en el archivo de log. Ninguno de los
dos es explotable desde afuera, pero los dos entregan acceso de administrador a
quien pueda leer el repositorio o los logs.

| Severidad | Cantidad |
|---|---|
| 🔴 Crítica | 0 |
| 🟠 Alta | 2 (ambas cerradas) |
| 🟡 Media | 1 |
| 🔵 Baja | 3 |

---

## Hallazgos

### [SEG-01] 🟠 ~~El seeder crea un administrador con contraseña fija y corre en cualquier entorno~~ · CORREGIDO 2026-11-03

- **Ubicación:** `database/seeders/DatabaseSeeder.php:15-21`
- **Categoría:** Credenciales por defecto (OWASP A07)
- **Confianza:** Confirmado en el código · **Verificado en producción el 2026-11-03: la cuenta no existe**

**Qué pasa:** el `firstOrCreate` que crea la cuenta `admin@goharv.com` con rol
`admin` tiene la contraseña escrita como literal de 18 caracteres en el archivo,
que está versionado en git. A diferencia del bloque de abajo —el del proyecto de
demostración, que sí está protegido con `app()->environment('local')`— **la
creación del administrador no tiene ninguna guarda de entorno**.

**Cómo se explota:** cualquiera con acceso al repositorio lee la contraseña. Si
en algún momento se corre `php artisan db:seed` o `migrate --seed` contra
producción —a mano o porque está en el comando de deploy—, queda una cuenta de
administrador activa con esa contraseña. Desde ahí se puede editar cualquier
cuenta, cambiar roles y borrar proyectos para siempre.

En la base local esa cuenta **no existe**, así que el seeder nunca corrió acá.
**Verificado el 2026-11-03: tampoco existe en producción.** El seeder nunca
corrió en ninguno de los dos entornos, así que la contraseña del repositorio
nunca llegó a ser una cuenta usable. El hallazgo era riesgo latente, no una
puerta abierta.

**Código actual:**

```php
public function run(): void
{
    $admin = User::firstOrCreate(
        ['email' => 'admin@goharv.com'],
        [
            'name'     => 'Responsable GoHarv',
            'password' => '<literal de 18 caracteres>',
            'role'     => 'admin',
        ]
    );

    if (app()->environment('local') && Project::count() === 0) {
        // ... el proyecto de demo sí está protegido
    }
}
```

**Corrección:**

```php
public function run(): void
{
    // El seeder arma el panel de desarrollo. En produccion las cuentas las
    // crea un responsable desde Equipo, con una clave que elige el.
    if (! app()->environment('local')) {
        $this->command->warn('El seeder solo corre en local. No se creo nada.');

        return;
    }

    $admin = User::firstOrCreate(
        ['email' => 'admin@goharv.com'],
        [
            'name'     => 'Responsable GoHarv',
            // Sin valor por defecto: si falta la variable, el seeder corta en
            // vez de crear un admin con una clave que esta en el repositorio.
            'password' => env('SEED_ADMIN_PASSWORD') ?: throw new RuntimeException(
                'Falta SEED_ADMIN_PASSWORD en el .env para poder sembrar el admin.'
            ),
            'role'     => 'admin',
        ]
    );

    if (Project::count() === 0) {
        // ... el resto igual
    }
}
```

**Por qué funciona:** la guarda de entorno impide que corra en producción aunque
alguien ejecute el comando por error, y sacar la contraseña del repositorio
elimina el secreto compartido. El `throw` evita el error clásico de poner un
valor por defecto que termina siendo la contraseña real.

**Además, hoy:** verificá en producción si existe `admin@goharv.com`. Si existe
y nadie la creó a mano desde Equipo, cambiale la contraseña ya.

---

### [SEG-02] 🟠 ~~La clave provisoria de las cuentas nuevas queda en texto plano en el log~~ · CORREGIDO 2026-11-03

- **Ubicación:** `app/Notifications/Bienvenida.php` + `config/mail.php` (`'default' => env('MAIL_MAILER', 'log')`)
- **Categoría:** Exposición de datos sensibles (OWASP A02)
- **Confianza:** Confirmado en local · **Verificado en producción el 2026-11-03: nunca se escribió ninguna clave**

**Qué pasa:** cuando se crea una cuenta, el panel manda un correo de bienvenida
con el usuario y la clave provisoria. Con `MAIL_MAILER=log` —que es lo que hay
hoy, porque el envío por Resend quedó pendiente— Laravel no manda nada: escribe
el correo **entero** en `storage/logs/laravel.log`, incluido el cuerpo HTML con
la clave en texto plano.

En el log local había **27 correos de bienvenida** con su clave legible
(vaciado el 2026-11-03).

**En producción nunca llegó a pasar.** `mail.default` es `log` allá, así que el
riesgo era real, pero el correo de bienvenida existe desde el 28/10/2026 y la
cuenta más nueva del panel es del 05/10/2026: ninguna de las seis pudo
generarlo. El arreglo entró 23 días antes del primer uso posible — suerte de
cronograma, no diseño, y por eso valía corregirlo igual.

**Cómo se explota:** cualquiera que pueda leer los logs obtiene credenciales
utilizables. En Laravel Cloud los logs se ven desde el panel de la plataforma y
se retienen. No hace falta explotar nada: alcanza con abrir el archivo. Y como
el log no rota por tamaño (`LOG_STACK=single`), las claves se acumulan.

**Código actual:** `MemberController::store()` manda la notificación con la
clave, y el canal `mail` la vuelca al log cuando no hay transporte configurado.

**Corrección:** no depender de qué `MAIL_MAILER` esté puesto. El panel ya sabe
si el correo sale de verdad —`Avisos::hayCorreo()`—, así que la notificación no
debería salir cuando el único destino es el log:

```php
// app/Support/Avisos.php
public static function mandar(Collection|array $destinatarios, Notification $aviso): void
{
    $destinatarios = $destinatarios instanceof Collection
        ? $destinatarios
        : collect($destinatarios);

    if ($destinatarios->isEmpty()) {
        return;
    }

    // Un aviso que solo lleva correo y no hay correo configurado no se manda:
    // escribirlo en el log deja la clave provisoria en texto plano, y ahi se
    // queda para siempre. La pantalla de alta ya avisa que hay que pasarla
    // por otro medio.
    if ($aviso->via($destinatarios->first()) === ['mail'] && ! static::hayCorreo()) {
        return;
    }

    try {
        Notificaciones::send($destinatarios, $aviso);
    } catch (\Throwable $e) {
        Log::error('No se pudo mandar un aviso.', [
            'aviso'         => $aviso::class,
            'destinatarios' => $destinatarios->pluck('id')->all(),
            'error'         => $e->getMessage(),
        ]);
    }
}
```

**Por qué funciona:** corta el único camino por el que una contraseña llega al
log, sin tocar los avisos que además van por campana o push. Cuando Resend esté
configurado, el correo vuelve a salir solo.

**Además, hoy:** vaciá `storage/logs/laravel.log` en local y en producción, y
cambiá las claves de las cuentas creadas hasta ahora que no hayan sido
cambiadas por su dueño.

---

### [SEG-03] 🟡 Las rutas de prueba de ARCA no piden sesión y pueden emitir comprobantes fiscales

- **Ubicación:** `routes/web.php:133-151` (rama `arca`)
- **Categoría:** Autorización ausente (OWASP A01)
- **Confianza:** Confirmado

**Qué pasa:** las seis rutas de `/arca/test` están fuera del grupo `auth`, una
de ellas con CSRF desactivado. Las protege únicamente
`if (app()->environment('local'))`. Si `ARCA_PRODUCTION` estuviera en `true`, lo
que emiten son **comprobantes fiscales reales**.

**Cómo se explota:** no es explotable hoy —la rama no está fusionada ni
desplegada, y en Laravel Cloud `APP_ENV` es `production`—. Se vuelve explotable
con un solo error de configuración: un `APP_ENV=local` en el servidor, o un
merge a `main` sin revisar. A partir de ahí, cualquiera sin sesión puede emitir
facturas contra el CUIT configurado.

**Corrección:** que no dependa de una sola condición. Además del entorno, pedir
sesión y rol de responsable:

```php
if (app()->environment('local')) {
    Route::middleware(['auth', 'admin'])
        ->prefix('arca/test')
        ->controller(ArcaTestController::class)
        ->group(function () {
            Route::get('/', 'pantalla')->name('arca.test');
            Route::post('emitir', 'emitir')->name('arca.test.emitir');
            // ... el resto igual, sin quitarle el CSRF al POST de factura
        });
}
```

Y en `ArcaService::__construct()`, una traba de último recurso:

```php
if ($config['production'] && ! app()->environment('production')) {
    throw new RuntimeException(
        'ARCA_PRODUCTION esta en true fuera de produccion. No se emite nada.'
    );
}
```

**Por qué funciona:** hacen falta dos errores en vez de uno, y la traba del
servicio impide emitir comprobantes reales desde una máquina de desarrollo aunque
las rutas queden expuestas.

---

### [SEG-04] 🔵 `symfony/mime` con una vulnerabilidad conocida y corrección disponible

- **Ubicación:** `composer.lock` — `symfony/mime v7.4.19`
- **Categoría:** Dependencia vulnerable (OWASP A06)
- **Confianza:** Confirmado (no explotable hoy)

**Qué pasa:** CVE-2026-45070, inyección de cabeceras de correo mediante
caracteres no permitidos en nombres de parámetros MIME. Severidad media según el
aviso. La rama 7.4 lo corrige en 7.4.22.

**Cómo se explota:** requiere que datos del usuario lleguen a un nombre de
parámetro MIME, que es lo que pasa típicamente con nombres de archivos adjuntos.
**El panel no manda adjuntos**, así que hoy no hay camino de explotación. Se
reporta porque la corrección cuesta un comando y porque el día que se adjunte un
PDF de factura, el camino aparece.

**Corrección:**

```bash
composer update symfony/mime
```

Verificado en seco: actualiza 7.4.19 → 7.4.22 sin arrastrar nada más.

---

### [SEG-05] 🔵 Las fotos de perfil se sirven por ID a cualquier usuario con sesión

- **Ubicación:** `app/Http/Controllers/ProfileController.php:85-93`
- **Categoría:** Exposición de información (OWASP A01)
- **Confianza:** Confirmado

**Qué pasa:** `GET /equipo/{user}/foto` sirve la foto de cualquier usuario. Está
detrás de `auth`, pero no verifica nada más.

**Cómo se explota:** un profesional externo —que tiene bloqueada la pantalla de
Equipo justamente para no ver quién trabaja en GoHarv— puede recorrer
`/equipo/1/foto`, `/equipo/2/foto`… y obtener las caras y el conteo de todas las
cuentas del panel. Es la misma información que Equipo le niega.

**Corrección:**

```php
public function avatar(Request $request, User $user): Response
{
    // Las caras del equipo son para el equipo. El externo ve las de la gente
    // con la que comparte proyecto —ahi su avatar aparece en tareas y notas—
    // y nada mas: recorrer IDs no puede darle la lista completa.
    abort_unless(
        $request->user()->isInternal()
            || $request->user()->id === $user->id
            || $request->user()->comparteProyectoCon($user),
        404,
    );

    $avatar = $user->avatar()->first();
    // ... el resto igual
}
```

Con un método nuevo en `User`:

```php
/** Si comparten al menos un proyecto, como responsable o colaborador. */
public function comparteProyectoCon(User $otro): bool
{
    return Project::query()
        ->visibleTo($this)
        ->where(fn ($q) => $q->where('owner_id', $otro->id)
            ->orWhereHas('collaborators', fn ($c) => $c->whereKey($otro->id)))
        ->exists();
}
```

**Por qué funciona:** reutiliza el scope `visibleTo` que ya define qué proyectos
ve cada uno, así que no hay una segunda definición de visibilidad que pueda
quedar desincronizada. Devuelve 404 y no 403 para no confirmar que el ID existe.

---

### [SEG-06] 🔵 El log no rota y guarda la base de datos en los mensajes de error

- **Ubicación:** `.env` — `LOG_STACK=single`, `LOG_LEVEL=debug`
- **Categoría:** Exposición de información / higiene
- **Confianza:** Confirmado

**Qué pasa:** `storage/logs/laravel.log` tiene 1,3 MB y crece sin límite porque
el canal es `single` y no `daily`. Con `LOG_LEVEL=debug` se vuelcan las consultas
SQL completas de los errores, incluidos nombres de base, host y —en un caso
verificado— la URL de suscripción push con su token.

**Corrección:** en producción, `LOG_STACK=daily` y `LOG_LEVEL=error`. En local
se puede dejar `debug`, pero conviene vaciar el archivo cada tanto.

---

## Lo que está bien

No hace falta tocarlo y conviene no romperlo al corregir lo de arriba.

**Autorización.** Cuatro policies (`ProjectPolicy`, `TaskPolicy`,
`ProjectUpdatePolicy`, `UserPolicy`) cubren todas las acciones sobre modelos, y
los controladores llaman a `authorize()` en cada método, no solo en los de
lectura. El scope `Project::visibleTo()` filtra por rol en el tablero, los
contadores, los archivados y el buscador desde un solo lugar, en vez de repetir
la condición en cada consulta.

**Sin escalada de privilegios.** `ProfileController::update()` solo acepta nombre
y correo: no hay forma de mandarse `role` ni `is_active` desde el perfil. Los
cambios de rol están en rutas con middleware `admin`, con guarda para que nadie
se quite los permisos a sí mismo. `UserPolicy` deliberadamente no tiene atajo
`before()` para admin, justamente para que esa regla valga también para ellos.

**Sin inyección SQL.** Los siete usos de SQL crudo se siguieron hasta el origen:
seis son cadenas fijas y el séptimo (`Project::sequence()`) pasa los valores por
`$pdo->quote()` y recibe el nombre de columna desde literales del propio código.

**Sin XSS.** Un solo `{!! !!}` en todo el proyecto, sobre un array de rutas SVG
definido en el mismo archivo. Los tres `innerHTML` del JavaScript arman marcado
constante. Los datos de PHP a JS van con `@json`. Hay CSP con nonce por request.

**Autenticación.** Límite de 5 intentos por correo e IP, mensaje de error
genérico que no revela si la cuenta existe, `session()->regenerate()` al entrar y
`invalidate()` más `regenerateToken()` al salir. El middleware
`EnsureUserIsActive` corta la sesión de una cuenta dada de baja en el acto, no
solo en el próximo login.

**Cabeceras.** `SecurityHeaders` pone CSP, `X-Frame-Options`,
`X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` y HSTS, esta
última solo sobre HTTPS para no romper el acceso local.

**Subida de archivos.** Las fotos de perfil se validan con `image`, `mimes` y
`max:4096`, se reprocesan con GD y se guardan **como BLOB en la base**, no en el
disco. No hay forma de subir un `.php` ni de que quede nada ejecutable.

**Secretos.** `.env` nunca se commiteó, está en `.gitignore`, y no hay dumps,
claves ni certificados versionados. `public/` tiene solo lo que debe.

---

## Plan de acción

**Hoy — hecho el 2026-11-03**
1. ~~SEG-01~~ — el seeder ya no crea ninguna cuenta. El primer responsable sale
   de `php artisan goharv:responsable`, que pide la clave por teclado. Siete
   tests lo fijan, incluido uno que falla si vuelve a aparecer un `'password'`
   escrito en el seeder.
2. ~~SEG-02~~ — `Avisos::mandar()` no manda avisos que solo van por correo
   cuando no hay correo configurado. Verificado: crear una cuenta con
   `MAIL_MAILER=log` ya no hace crecer el archivo de log ni un byte.

**Queda de esos dos, y es manual:**
- ~~Verificar en producción si existe `admin@goharv.com`~~ — hecho el
  2026-11-03: no existe. El seeder nunca corrió allá.
- ~~Vaciar `storage/logs/laravel.log`~~ — hecho el 2026-11-03 en local (de
  1,4 MB a 0). En producción no hacía falta: Laravel Cloud no escribe a disco
  —usa `laravel-cloud-socket`— y nunca hubo una clave que escribir.
- ~~Cambiar las claves de las cuentas creadas hasta hoy~~ — no aplica: ninguna
  cuenta se creó después de que existiera el correo de bienvenida.

**Las dos altas quedan cerradas:** código corregido y verificado que ninguna
llegó a materializarse en producción.

**Esta semana**
3. SEG-03 — sumar `auth` y `admin` a las rutas de ARCA antes de que la rama se
   acerque a `main`.
4. SEG-04 — `composer update symfony/mime`.

**Cuando toque**
5. SEG-05 y SEG-06.

---

## Checklist antes de subir a producción

- [ ] `APP_DEBUG=false` y `APP_ENV=production` en Laravel Cloud
- [ ] `APP_KEY` de producción distinta de la local
- [x] `admin@goharv.com` no existe en producción (verificado 2026-11-03)
- [ ] El comando de deploy **no** incluye `db:seed` ni `migrate --seed`
- [ ] `MAIL_MAILER` configurado, o SEG-02 corregido
- [ ] `LOG_STACK=daily` y `LOG_LEVEL=error`
- [ ] `composer audit` sin avisos
- [ ] La rama `arca` no está fusionada, o SEG-03 está corregido
- [ ] Recuperación de contraseña por correo (hoy depende de un responsable)
- [ ] Zona horaria del panel: hoy corre en UTC y el equipo está en Argentina
- [ ] Backups de la base configurados y no accesibles desde la web
