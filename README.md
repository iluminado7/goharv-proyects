# GoHarv — Panel de proyectos

Tablero interno para centralizar los proyectos del equipo: cargarlos, ordenarlos
por prioridad, seguir en qué punto está cada uno y entrar directo a su enlace.
Reemplaza el seguimiento disperso entre chats, planillas y memoria de cada uno.

**Stack:** Laravel · Blade (sin capa JS) · autenticación propia sobre sesiones · MySQL/MariaDB

---

## Qué hace hoy

**Acceso por miembro.** Login con correo y clave, sesión propia, límite de 5
intentos fallidos por minuto y bloqueo de cuentas dadas de baja. Dos niveles:
*responsable* (administra el equipo) y *miembro*.

**Archivar y restaurar.** Archivar saca el proyecto del tablero sin borrar nada.
Los archivados tienen su pantalla en `/proyectos/archivados`, con buscador, y
vuelven con un clic. Las dos cosas quedan asentadas en el historial.

**Proyectos.** Nombre, detalle, responsable, colaboradores, prioridad
(alta / media / baja), estado y fecha de entrega opcional. Cada proyecto lleva
todos los enlaces que necesite (repo, Drive, staging, diseño); el primero de la
lista es el que abre el botón del tablero. Archivar no borra: la tabla usa
`softDeletes`.

**Estados en secuencia.** Nuevo → Inicio → En desarrollo → Terminado, con barra
de avance de cuatro tramos en el tablero. Los estados son enums de PHP, así que
sumar uno nuevo se hace en un solo archivo.

**Historial por proyecto.** Todo cambio de estado pasa por `Project::moveTo()`
y queda registrado en `project_updates` con autor, fecha, estado anterior y
comentario. No hay manera de mover un proyecto sin dejar rastro.

**Orden y filtros.** Por prioridad (default), por estado, por último movimiento
o alfabético. Filtros por estado, responsable y búsqueda de texto, de a 30 por
página. La búsqueda usa el índice fulltext donde el motor lo soporta.

**Quién edita qué.** `ProjectPolicy`: el proyecto lo edita y lo mueve de estado
quien está metido en él —responsable o colaborador— más los responsables del
panel. El resto del equipo lo ve, pero no lo toca. Archivar queda para el
responsable del proyecto y los del panel.

**Mi perfil.** Cada uno cambia su nombre, su correo y su clave (pidiendo la
actual). Ahí figura su rol; el rol y el alta o baja los sigue manejando el
responsable del panel.

**Menú y fondo.** Header con Proyectos, Equipo (solo responsables) y Mi perfil,
marcando en cuál se está parado. El botón ☀/☾ alterna entre fondo negro y claro:
la preferencia va en una cookie y el tema lo escribe el servidor en el `<html>`,
así que no hay JS ni parpadeo al cargar.

**Gestión del equipo.** El responsable suma miembros, cambia permisos y da de
baja. Hay una validación que impide dejar el panel sin ningún responsable activo.

---

## Instalación

```bash
laravel new goharv-panel
cd goharv-panel
```

Copiar el contenido de este repositorio sobre la instalación respetando las
rutas. Reemplazan a los archivos por defecto: `routes/web.php`,
`app/Models/User.php`, `database/seeders/DatabaseSeeder.php`.

En `.env`:

```
DB_CONNECTION=mysql
DB_DATABASE=goharv_panel
DB_USERNAME=
DB_PASSWORD=
APP_LOCALE=es
APP_TIMEZONE=America/Argentina/Buenos_Aires
```

Registrar el middleware de administrador en `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
    ]);
})
```

En Laravel 10 o anterior el alias va en `app/Http/Kernel.php`, dentro de
`$middlewareAliases`.

```bash
php artisan migrate --seed
php artisan serve
```

Primer ingreso: `admin@goharv.com` con la clave del seeder. Cambiarla de
inmediato y cargar al resto del equipo desde *Equipo*.

---

## Estructura de la base

| Tabla | Para qué |
|---|---|
| `users` | Se le agregan `role` (admin/member) e `is_active`. |
| `projects` | Nombre, slug, detalle, estado, prioridad, `owner_id`, `due_date`, `started_at`, `completed_at`. Con `softDeletes` e índice fulltext sobre nombre y detalle. |
| `project_links` | Enlaces del proyecto: `label`, `url`, `position`. |
| `project_user` | Colaboradores además del responsable. |
| `project_updates` | Historial: autor, comentario, `status_from`, `status_to`. |

Los archivados no son otra tabla: son filas de `projects` con `deleted_at` cargado.

---

## Mapa de archivos

```
app/Enums/            ProjectStatus, ProjectPriority, UserRole
app/Models/           Project, ProjectLink, ProjectUpdate, User
app/Http/Controllers/ ProjectController, MemberController, ProfileController,
                      ThemeController, Auth/LoginController
app/Http/Middleware/  EnsureUserIsAdmin
app/Policies/         ProjectPolicy
resources/views/      layouts/app, auth/login, projects/*, members/index,
                      profile/edit, partials/theme-toggle, pagination/goharv
public/css/           goharv.css
tests/Feature/        Login, Project, ProjectHistory, ProjectPolicy, Profile,
                      Menu, Theme
```

---

## Lo que falta

### Bloqueantes antes de producción

1. **Recuperación de clave.** No existe. Cada uno ya se cambia la clave desde
   *Mi perfil*, pero el que la olvida sigue dependiendo de que el responsable se
   la resetee a mano. Falta `ForgotPasswordController`, la tabla
   `password_reset_tokens` (Laravel ya la trae) y configurar el mailer.
2. **Configurar el envío de correo.** Sin esto no funciona ni el punto anterior
   ni ninguna notificación futura.
3. **HTTPS y `APP_DEBUG=false` en el servidor.** El login viaja en texto plano
   sobre HTTP. El `trustProxies` de `bootstrap/app.php` hoy confía en cualquier
   proxy (`at: '*'`), que sirve para mirar el panel por ngrok; en producción hay
   que dejar ahí la IP del proxy real, o alguien puede falsear el host de las
   URLs que genera el panel.
4. **Backups automáticos de la base.** El panel pasa a ser la fuente de verdad
   de los proyectos; perder esa tabla es perder el historial completo.

### Funcionalidad pendiente

5. **Comentarios sueltos.** `project_updates` ya acepta un `body` sin cambio de
   estado, pero no hay interfaz para dejar una nota sin mover el proyecto.
6. **Adjuntos.** Subir archivos al proyecto (`spatie/laravel-medialibrary` o
   storage nativo).
7. **Notificaciones.** Avisar al responsable cuando le asignan un proyecto o
   cuando se vence una fecha de entrega.
8. **Tablero por columnas.** El listado ordenado funciona bien, pero una vista
   tipo kanban con las cuatro columnas puede leerse más rápido. Requiere JS,
   así que va contra la decisión de Blade puro: evaluarlo antes.
9. **Exportar.** Un CSV del estado de todos los proyectos para reportes.
10. **Ordenar los enlaces a mano.** La columna `position` está, pero el orden
    hoy es el de carga en el formulario. Reordenar sin JS implica flechas
    arriba/abajo con un POST por clic.

### Decisiones a tomar

11. **Qué pasa con un proyecto terminado.** ¿Se archiva solo a los X días? ¿Queda
    en el tablero para siempre? Sin una regla, el listado se llena de terminados.
    Ahora que archivar y restaurar es un clic, la salida barata es archivarlos a
    mano hasta decidir si conviene automatizarlo.
12. **Borrado definitivo.** Los archivados se acumulan para siempre. Falta
    decidir si alguien puede vaciarlos de verdad y quién.

---

## Lo que se cerró

- **Orden portable.** Los scopes ya no usan `FIELD()` de MySQL: `Project::sequence()`
  arma un `CASE WHEN` con los valores del enum, así que corre igual en MySQL,
  PostgreSQL y el SQLite de los tests.
- **Paginación.** `ProjectController@index` pagina de a 30 conservando filtros y
  orden (`withQueryString`), con una vista propia en `pagination/goharv`.
- **`ProjectPolicy`.** La autorización salió de los `abort_unless` sueltos y
  quedó en un solo archivo; las vistas esconden lo que no se puede tocar. Se
  descubre sola por convención, no hace falta registrarla.
- **Tests.** 56 casos sobre login y bloqueos, alta y edición de proyectos,
  enlaces, colaboradores, permisos, perfil, menú, fondo, URLs detrás de un proxy
  archivados y —sobre todo— que `moveTo()` escriba el historial.
  `php artisan test`.
- **Índice de texto completo.** Migración con `fullText` sobre `name` y
  `description`; `Project::scopeSearch()` lo usa en MySQL/MariaDB/PostgreSQL con
  términos de 4 letras o más y cae al `LIKE` de antes en el resto de los casos.
- **Enlaces múltiples.** Tabla `project_links`. La migración mueve el `url` que
  había en cada proyecto a un enlace *Principal* y después borra la columna, así
  que no hay que hacer nada a mano; el `down()` la reconstruye.
- **Mi perfil.** Pestaña propia en `/perfil` con los datos de la cuenta y el
  cambio de clave, que exige la clave actual.
- **Menú completo y fondo claro.** El header ya tiene su botón a Proyectos, el
  rol salió de ahí y quedó en el perfil, y el fondo se alterna entre negro y
  claro desde el header o desde el login.
- **Quién edita qué, decidido.** Edita quien está metido en el proyecto. La regla
  quedó escrita en `ProjectPolicy` y cubierta por `ProjectPolicyTest`, así que si
  algún día cambia, se cambia en un solo lugar.

---

## Convenciones

- Rutas en español (`/proyectos`, `/equipo`), código y base de datos en inglés.
- Los estados y prioridades no se escriben como strings sueltos: siempre vía
  `App\Enums`.
- Los cambios de estado van por `Project::moveTo()`, nunca con un `update()`
  directo sobre la columna `status`, o se pierde el historial.
- Los permisos van por `ProjectPolicy` (`$this->authorize(...)` en el controlador,
  `@can` en las vistas), no con `abort_unless` sueltos.
- Nada de SQL propio de un motor. Si hace falta ordenar por una secuencia, va un
  `CASE WHEN` armado desde el enum.
- Nada de `<select multiple>`: para elegir varios van checkboxes, que no piden
  Ctrl+clic ni explicación.
- Los colores salen de las variables CSS (`--bg`, `--panel`, `--line`, `--ink`,
  `--muted`, `--faint`, `--track`), nunca de un hex suelto, o el fondo claro se
  rompe.