# GoHarv — Panel de proyectos

Tablero interno para centralizar los proyectos del equipo: cargarlos, ordenarlos
por prioridad, seguir en qué punto está cada uno y entrar directo a su enlace.
Reemplaza el seguimiento disperso entre chats, planillas y memoria de cada uno.

**Stack:** Laravel · Blade (sin capa JS) · autenticación propia sobre sesiones · MySQL/MariaDB

Este archivo es la documentación técnica. Para el equipo que va a usar el panel
está la [Guía del panel](GUIA.md), que explica cómo se usa sin hablar de código.

---

## Qué hace hoy

**Acceso por cuenta.** Login con correo y clave, sesión propia, límite de 5
intentos fallidos por minuto y bloqueo de cuentas dadas de baja. Tres roles:
*responsable* (administra el equipo), *socio* (ve todo el tablero) y
*profesional externo*, que solo ve los proyectos que le delegaron y no entra ni
a Equipo ni a Actividad.

**Avisos en el celular.** Notificaciones push web, los mismos tres avisos que
el correo. Cada dispositivo es una suscripción aparte y se activa desde *Mi
perfil*, y el panel lo ofrece una vez por dispositivo la primera vez que
alguien entra (se recuerda en `localStorage`, no en la base, porque la decisión
es por dispositivo). El permiso del navegador se pide **solo desde el clic**:
pedirlo al cargar la página hace que Chrome y Firefox lo bloqueen solos y ya no
se pueda volver a preguntar. Andan con el panel cerrado. En Android funciona desde el navegador;
**en iPhone solo si la persona agregó el panel a la pantalla de inicio** — en
una pestaña de Safari el permiso ni se puede pedir. Las suscripciones vencidas
las borra solo el paquete cuando el envío devuelve 410. Si no hay claves VAPID
cargadas, el canal no se suma y el panel funciona igual.

**Correo.** Listo pero **apagado**: falta un dominio verificado en Resend
(`goharv.com.ar` no está, y el dominio de Laravel Cloud no sirve porque su DNS
no es nuestro). Mientras tanto corre con `MAIL_MAILER=log`: no sale nada, el
panel funciona igual y la campana avisa como siempre. Para prenderlo alcanza
con verificar un dominio y poner `MAIL_MAILER=resend` más `MAIL_FROM_ADDRESS`
en ese dominio; no hay que tocar código. `Avisos::hayCorreo()` dice si está
activo, y las pantallas que prometen un mail lo consultan antes de prometerlo.

Tres avisos salen por mail además de la campana: te
asignaron un proyecto, te sumaron a un proyecto y te asignaron una tarea. Los
informativos —nota, cambio de estado, tarea completada— se quedan en la campana
para no convertir la casilla en ruido. Lo decide `NotificationType::vaPorCorreo()`.
El transporte es Resend, conectado desde Laravel Cloud.

**Alta con correo de bienvenida.** Crear una cuenta manda el usuario y la clave
provisoria, y la cuenta nace con `must_change_password`: el panel queda cerrado
hasta que esa persona elija una clave suya. Lo mismo cuando un responsable le
resetea la clave a alguien.

**Edición de cuentas.** Solo el responsable del panel: nombre, correo, permisos
y, opcionalmente, restablecer la clave de quien la olvidó. Queda en la bitácora
con el detalle de qué se tocó.

**Baja de cuentas.** Un responsable da de baja a cualquiera y un socio a los
profesionales externos. La cuenta pierde el acceso en el acto y sale de todos
los desplegables, pero sus proyectos, notas, tareas y la bitácora quedan. Se
reactiva desde la misma pantalla. Reusa `is_active`: no hay un segundo estado
de "eliminado" que los trece filtros del panel tendrían que chequear aparte.

**Archivar y restaurar.** Archivar saca el proyecto del tablero sin borrar nada.
Los archivados tienen su pantalla en `/proyectos/archivados`, con buscador, y
vuelven con un clic. Las dos cosas quedan asentadas en el historial.

**Borrado definitivo.** Solo los responsables del panel, solo sobre proyectos ya
archivados, y con una pantalla previa donde hay que escribir el nombre del
proyecto. Se lleva el historial, los enlaces y los colaboradores en cascada: es
la única acción del panel que no se puede deshacer.

**Empresa por proyecto.** La consultora trabaja para varios clientes, así que
cada proyecto lleva el nombre de la empresa. Es texto libre —decisión del
equipo— con dos amortiguadores: el formulario sugiere las ya cargadas con un
`<datalist>` (HTML puro, sin JS) y el modelo normaliza los espacios al guardar,
para que la misma empresa no entre con tres grafías. El tablero filtra por
empresa y el buscador la incluye.

**Proyectos.** Nombre, detalle, responsable, colaboradores, prioridad
(alta / media / baja), estado y fecha de entrega opcional. Cada proyecto lleva
todos los enlaces que necesite (repo, Drive, staging, diseño); el primero de la
lista es el que abre el botón del tablero. Archivar no borra: la tabla usa
`softDeletes`.

**Estados en secuencia.** Nuevo → Inicio → En desarrollo → Terminado, con barra
de avance de cuatro tramos en el tablero. Los estados son enums de PHP, así que
sumar uno nuevo se hace en un solo archivo.

**Tareas por proyecto.** Cada proyecto tiene su lista, dentro de su ficha. Una
tarea lleva título, fecha límite opcional y una o varias personas a cargo, y
tiene dos estados: pendiente o completada. El estado se guarda como
`completed_at` nullable —vacío es pendiente— y de paso queda cuándo y quién la
completó.

Solo se puede asignar a gente del proyecto: `Task::assignTo()` cruza contra los
participantes y descarta el resto. Las rutas cuelgan del proyecto y usan
`scopeBindings`, así que una tarea de otro proyecto en la URL da 404.

Completar lo puede hacer cualquiera del proyecto y se deshace con un clic.
**Editar y borrar** son más cerrados —quien la pidió o el responsable del
proyecto—: cambian lo que otro tiene que hacer, o no se deshacen. El formulario
de edición se despliega en la misma fila con `<details>`.

El título acepta 500 caracteres. No se sacó el tope del todo porque un título
sin límite convierte el listado en un muro; para textos largos lo que falta es
un campo de detalle aparte.

Las cinco acciones —crear, editar, completar, reabrir, eliminar— quedan en la
bitácora con el título de la tarea. La campana avisa dos: cuando te asignan
una, y cuando completan una que te tocaba o que pediste. Reabrir y eliminar no
avisan: pasan poco y no piden que hagas nada.

**Historial por proyecto.** Todo cambio de estado pasa por `Project::moveTo()`
y queda registrado en `project_updates` con autor, fecha, estado anterior y
comentario. No hay manera de mover un proyecto sin dejar rastro.

**Comentarios.** Se puede dejar una nota en un proyecto sin moverlo de estado, y
queda en la misma línea de tiempo que los movimientos, con el punto hueco para
distinguirla. Comentar está abierto a todo el equipo, no solo a quienes
participan del proyecto: no cambia ningún dato y sirve para avisar algo. Los
saltos de línea se respetan (`white-space: pre-line`, sin imprimir HTML crudo).

Las notas las borra su autor, o cualquier responsable del panel. Los **cambios
de estado no se borran nunca**, ni por un admin: `ProjectUpdatePolicy` lo frena.
Son el registro de quién movió el proyecto, que es para lo que existe el panel.

**Orden y filtros.** Por prioridad (default), por estado, por último movimiento
o alfabético. Filtros por empresa, responsable y búsqueda de texto, de a 30 por
página. La búsqueda usa el índice fulltext donde el motor lo soporta.

Los contadores de arriba **son** el filtro por estado: muestran cuántos hay en
cada uno y llevan ahí con un clic. Se calculan con todos los filtros vigentes
menos el de estado, para que los números no se muevan al pararse en uno y se
pueda saltar de un estado a otro.

**Quién edita qué.** `ProjectPolicy`: el proyecto lo edita y lo mueve de estado
quien está metido en él —responsable o colaborador— más los responsables del
panel. El resto del equipo lo ve, pero no lo toca. Archivar queda para el
responsable del proyecto y los del panel.

La **prioridad** es la excepción: ordena el tablero de todo el equipo, así que
la decide el responsable del proyecto, no quien colabora. Se cambia desde la
ficha, y el formulario de edición respeta la misma regla —a quien no puede, el
campo se le muestra como texto y el servidor descarta lo que llegue por él—.

**Mi perfil.** Cada uno cambia su nombre, su correo, su clave (pidiendo la
actual) y su **foto**. Quien no sube ninguna queda con un círculo de iniciales
en un color derivado de su nombre, así que nunca hay un hueco.

La foto se guarda **en la base, en la tabla `user_avatars`**, no en disco: el
filesystem de Laravel Cloud se borra en cada deploy y un bucket costaría aparte.
Quien sube la foto la encuadra antes, en un cuadro con zoom y arrastre
(`avatar-cropper`); sin JavaScript se sube igual y el servidor recorta al
centro. En las dos vías el servidor recodifica a JPEG de 256px con GD (una foto típica
queda en 2–20 KB), lo que de paso descarta metadatos y cualquier cosa escondida
en el archivo original. Está en su propia tabla para que el binario no viaje en
cada listado de usuarios.

La URL de la foto lleva su fecha (`/equipo/{id}/foto?v=…`), que se guarda
duplicada en `users.avatar_updated_at`. Eso resuelve dos cosas: cambiar la foto
cambia la dirección, así que ninguna capa de caché puede servir la vieja; y
saber si alguien tiene foto no cuesta una consulta por cara dibujada —en el
historial de un proyecto era una por línea—. La columna la mantienen los
eventos de `UserAvatar`, no el controlador. Ahí figura su rol; el rol y el alta o baja los sigue manejando el
responsable del panel.

**Se instala en el celular.** Es una PWA: desde Android el navegador ofrece
instalarla solo, y desde iPhone se agrega con Compartir → Agregar a inicio.
Queda con ícono propio y abre a pantalla completa. Sin conexión muestra una
pantalla propia en vez del error del navegador.

**Menú y fondo.** Header con Proyectos, Equipo, Actividad (solo responsables) y
Mi perfil, marcando en cuál se está parado. En pantallas chicas los botones se
colapsan detrás de un botón de menú, hecho con un checkbox y CSS: el mismo HTML
sirve para las dos pantallas y decide la media query. El botón ☀/☾ alterna entre fondo negro y claro:
la preferencia va en una cookie y el tema lo escribe el servidor en el `<html>`,
así que no hay JS ni parpadeo al cargar.

**Gestión del equipo.** *Equipo* la ve todo el panel: es a quién pedirle algo.
Los controles —sumar, cambiar permisos, dar de baja— solo aparecen para los
responsables, y las rutas que los ejecutan siguen detrás del middleware `admin`:
esconder el formulario nunca es la defensa. A quien no administra se le muestran
solo las cuentas activas. Hay una validación que impide dejar el panel sin
ningún responsable activo, y nadie puede sacarse a sí mismo.

**Campana de notificaciones.** Avisa cuatro cosas, y solo cuando te las hizo
otro en un proyecto donde estás: te asignaron como responsable, te sumaron como
colaborador, comentaron, o movieron el estado. Nunca tus propias acciones,
nunca proyectos ajenos, nunca a cuentas dadas de baja.

Se abre en un cuadro desde la campana del header, hecho con `<details>` —un
desplegable nativo de HTML, sin JavaScript—. Muestra las últimas seis y enlaza
a `/notificaciones`, que tiene la lista completa con paginado.

Usa la tabla `notifications` del framework con una sola clase, `ProjectEvent`,
que distingue los casos por un enum. El día que haya correo configurado se le
suma `toMail()` y `'mail'` al `via()`: los lugares que disparan el aviso no se
tocan. Los datos del proyecto y del actor se guardan congelados en el payload,
así que un aviso viejo se sigue leyendo aunque el proyecto ya no exista.

**Bitácora.** `activity_logs` registra accesos (ingreso, salida, clave
incorrecta, correo inexistente, intento con cuenta de baja) y lo que pasa con
los proyectos (alta, cambio de estado, cambio de prioridad, nota nueva,
archivado, restaurado, eliminado). Guarda quién, cuándo, desde qué IP y el
detalle del cambio. Se lee en `/actividad`, solo para responsables del panel.

Son tres cosas distintas y conviene no mezclarlas: `project_updates` es el
historial visible de cada proyecto y parte de la herramienta; la **bitácora** es
para auditar el panel entero y la mira un responsable cuando quiere; la
**campana** es personal y trae solo lo que pide tu atención. Si un evento no
cumple "otro te lo hizo y podés hacer algo", va a la bitácora y no a la campana.

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
| `users` | Se le agregan `role` (admin/member/external) e `is_active`. |
| `projects` | Nombre, slug, detalle, `client`, estado, prioridad, `owner_id`, `due_date`, `started_at`, `completed_at`. Con `softDeletes` e índice fulltext sobre nombre, detalle y empresa. |
| `tasks` | Tareas del proyecto: `title`, `due_date`, `completed_at`, `completed_by`, `created_by`. |
| `task_user` | A quiénes va dirigida cada tarea. |
| `project_links` | Enlaces del proyecto: `label`, `url`, `position`. |
| `user_avatars` | Foto de perfil: `user_id`, `mime`, `image` (MEDIUMBLOB). |
| `notifications` | La del framework. Avisos personales, con estado de leído. |
| `activity_logs` | Bitácora: `user_id`, `action`, `project_id`, `subject`, `detail`, `ip`, `created_at`. Append-only, sin `updated_at`. |
| `project_user` | Colaboradores además del responsable. |
| `project_updates` | Historial: autor, comentario, `status_from`, `status_to`. |

Los archivados no son otra tabla: son filas de `projects` con `deleted_at` cargado.

---

## Mapa de archivos

```
app/Enums/            ProjectStatus, ProjectPriority, UserRole, ActivityAction,
                      NotificationType
app/Notifications/    ProjectEvent
app/Models/           Project, ProjectLink, ProjectUpdate, Task, User,
                      UserAvatar, Activity
app/Support/          AvatarImage, Assets
app/Http/Controllers/ ProjectController, MemberController, ProfileController,
                      ThemeController, Auth/LoginController
app/Http/Middleware/  EnsureUserIsAdmin, EnsureUserIsActive, SecurityHeaders
app/Policies/         ProjectPolicy, ProjectUpdatePolicy, TaskPolicy
resources/views/      layouts/app, auth/login, projects/*, members/index,
                      profile/edit, partials/theme-toggle, pagination/goharv
public/css/           goharv.css
public/                manifest.webmanifest, sw.js, offline.html, icons/
tests/Feature/        Login, Project, ProjectHistory, ProjectPolicy, Profile,
                      Menu, Theme, ProjectComment, ArchivedProject, ProxyUrl,
                      Pwa
```

---

## Lo que falta

### Bloqueantes antes de producción

1. **Recuperación de clave.** No existe. Cada uno ya se cambia la clave desde
   *Mi perfil*, y el responsable puede restablecérsela desde *Equipo → Editar*,
   pero sigue necesitando avisarle por fuera del panel. Falta `ForgotPasswordController`, la tabla
   `password_reset_tokens` (Laravel ya la trae) y configurar el mailer.
2. **Configurar el envío de correo.** Sin esto no funciona ni el punto anterior
   ni ninguna notificación futura.
3. **HTTPS y `APP_DEBUG=false` en el servidor.** El login viaja en texto plano
   sobre HTTP.
4. **Backups automáticos de la base.** El panel pasa a ser la fuente de verdad
   de los proyectos; perder esa tabla es perder el historial completo.

### Funcionalidad pendiente

5. **Adjuntos.** Subir archivos al proyecto (`spatie/laravel-medialibrary` o
   storage nativo).
6. **Avisos por correo y por vencimiento.** La campana ya avisa dentro del
   panel. Falta que esos mismos avisos salgan por mail (depende del punto 2) y
   que haya uno por fecha de entrega próxima, que necesita una tarea programada
   —hoy no hay ninguna configurada, y ningún proyecto tiene fecha cargada—.
7. **Tablero por columnas.** El listado ordenado funciona bien, pero una vista
   tipo kanban con las cuatro columnas puede leerse más rápido. Requiere JS,
   así que va contra la decisión de Blade puro: evaluarlo antes.
8. **Exportar.** Un CSV del estado de todos los proyectos para reportes.
9. **Ordenar los enlaces a mano.** La columna `position` está, pero el orden
    hoy es el de carga en el formulario. Reordenar sin JS implica flechas
    arriba/abajo con un POST por clic.

11. **Limpieza de la bitácora.** `activity_logs` crece y nada la purga. Con
    cinco personas tarda años en pesar, pero en algún momento va a querer un
    comando que borre lo anterior a X meses.

### Decisiones a tomar

10. **Qué pasa con un proyecto terminado.** ¿Se archiva solo a los X días? ¿Queda
    en el tablero para siempre? Sin una regla, el listado se llena de terminados.
    Ahora que archivar y restaurar es un clic, la salida barata es archivarlos a
    mano hasta decidir si conviene automatizarlo.

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
- **Tests.** 239 casos sobre login y bloqueos, alta y edición de proyectos,
  enlaces, colaboradores, permisos, perfil, menú, fondo, URLs detrás de un proxy
  archivados, comentarios, borrado definitivo y —sobre todo— que `moveTo()`
  escriba el historial.
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
- **Instalable en el celular.** Manifest, iconos generados con GD, service
  worker y pantalla de sin conexión. Toques de 44px donde el puntero es grueso,
  campos de 16px para que Safari no haga zoom, y un breakpoint en 520px. El
  service worker **no cachea páginas** a propósito: el panel muestra datos del
  equipo y un teléfono se pierde o se presta.
- **Comentarios sueltos.** `Project::comment()` guarda la nota con el mismo
  estado en las dos puntas, así `isStatusChange()` da false y el historial la
  muestra distinta sin necesidad de una columna nueva.
- **Cabeceras de seguridad.** `SecurityHeaders` agrega CSP, `X-Frame-Options`,
  `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` y HSTS
  (esta última solo si la request ya es HTTPS). La CSP usa un **nonce por
  request** para los dos scripts del panel en lugar de abrir `unsafe-inline`:
  si algún día se cuela un script inyectado, no va a tener el nonce.
- **Reglas de clave en un solo lugar.** `Password::defaults()` en
  `AppServiceProvider`; en producción suma `uncompromised()`, que contrasta
  contra bases de claves filtradas. Fuera de producción no, para no meter una
  llamada de red en los tests.
- **Límites de intentos** en el cambio de clave (6/min) y en los comentarios
  (30/min), además del login que ya los tenía.
- **El login no delata cuentas de baja.** Antes ese mensaje aparecía recién
  después de acertar la clave, o sea que confirmaba que la clave era correcta.
- **Baja efectiva.** `EnsureUserIsActive` corre en todo el grupo `web`: si la
  cuenta está dada de baja, cierra la sesión en el acto. Antes el chequeo estaba
  solo en el login, así que una sesión ya abierta seguía leyendo el tablero
  entero y un responsable dado de baja podía reactivarse solo desde *Equipo*.
- **El host no se hereda del proxy.** `trustProxies` confía en el esquema, el
  puerto y la IP de origen, pero no en `X-Forwarded-Host`: esa cabecera la manda
  cualquiera y serviría para que el panel genere enlaces hacia otro dominio.
  Importa sobre todo el día que haya recuperación de clave por correo.
- **Cookie de sesión `secure` sola en producción.** `config/session.php` la
  activa cuando `APP_ENV=production`, sin depender de que alguien se acuerde.
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
- Anotar en la bitácora va siempre por `Activity::anotar()`, que traga sus
  propios errores: un log roto nunca puede voltear la acción que lo originó.
- Los permisos van por `ProjectPolicy` (`$this->authorize(...)` en el controlador,
  `@can` en las vistas), no con `abort_unless` sueltos.
- Nada de SQL propio de un motor. Si hace falta ordenar por una secuencia, va un
  `CASE WHEN` armado desde el enum.
- El JavaScript del panel son ocho partials: `pwa-register` (registra el
  service worker), `password-toggle` (el ojito de la clave), `avatar-cropper`
  (encuadrar la foto), `dropdown-close` (cerrar la campana y la barra del
  celular al tocar afuera), `instant-toggles` (plegar la barra y cambiar el
  fondo sin recargar) y los tres de push: `push-core` (la lógica compartida),
  `push-toggle` (el botón de Mi perfil) y `push-invite` (el ofrecimiento de la
  primera vez). Todos hacen cosas que el servidor no puede hacer.
- **Push es la única excepción a "degradar bien".** Los otros cinco funcionan
  sin JavaScript en una versión reducida; push directamente no existe sin él.
  Lo que sí hace es no romper nada: el marcado ni se manda si no hay claves
  VAPID, y quien no tenga JS sigue viendo los avisos en la campana igual que
  siempre. La regla ya no es "nada de JS" sino
  **nada que el HTML o un formulario puedan resolver solos**: antes de sumar
  otro, buscar el elemento nativo —`<details>`, `<datalist>`— que haga el
  trabajo.
- Todo lo que el navegador pueda cachear lleva su versión en la URL: el CSS con
  `Assets::versioned()` y las fotos con `?v=` + la fecha de la foto. Si algún
  día se suma otro archivo así, que siga la misma regla: sin versión en la URL,
  el cambio tarda días en verse y se termina peleando con el caché.
- Nada de `<select multiple>`: para elegir varios van checkboxes, que no piden
  Ctrl+clic ni explicación.
- Cualquier `<script>` nuevo necesita `nonce="{{ $cspNonce }}"` o la CSP lo
  bloquea sin decir nada en pantalla.
- El diseño lo manda la **Marca Madre GoHarv** (skill `goharv-design`), que
  gana sobre cualquier estilo que ya existiera acá. De ahí salen: dos fuentes y
  nada más —Montserrat para títulos, etiquetas, botones y números
  (`--fuente-titulo`, `--fuente-numeros`), Roboto para el texto corrido
  (`--fuente-cuerpo`)—, el dorado `#BFAE76` como único acento, `border-radius: 0`
  en todo salvo los avatares y el punto de estado, y nada de sombras ni
  gradientes: lo que separa son bordes de 1px.
- Los colores salen de los roles de la guía (`--text`, `--text-muted`, `--label`,
  `--accent`, `--accent-soft`, `--surface`, `--border`, `--success`, `--error`,
  `--warning`), nunca de un hex suelto. Los nombres cortos (`--bg`, `--panel`,
  `--line`, `--ink`, `--muted`, `--faint`, `--track`) siguen existiendo como
  alias de esos roles para las reglas viejas; en lo nuevo van los roles.
- El armazón es el de la guía: topbar de lado a lado arriba, barra lateral a la
  izquierda y lienzo con pie. Lo pone `layouts/app`, así que una vista nueva no
  tiene que armar nada: solo `@section('content')`, y `@section('banda')` si
  quiere la franja de encabezado.
- La barra lateral se pliega con una **cookie** (`SidebarController`), igual que
  el tema: con un checkbox volvería a abrirse en cada navegación. El botón es un
  `<form>` que postea, y `partials/instant-toggles` lo intercepta para mover la
  clase en el acto y mandar ese mismo POST por atrás, así no hay recarga. Sin JS
  el form sigue andando con su viaje al servidor. El botón de fondo claro/oscuro
  usa ese mismo partial: los dos íconos viajan siempre en el HTML y el CSS
  muestra el que corresponde al `data-theme`, así cambiarlo es cambiar un
  atributo.
- El checkbox queda solo para abrir la barra sobre el contenido en pantallas de
  menos de 1024px, donde es algo de un momento y no una preferencia.
- Plegada, los nombres siguen en el HTML: los esconde el CSS. El servidor manda
  siempre lo mismo, y cada ítem lleva `title` y `aria-label` para cuando solo se
  ve el ícono.
- Los íconos son Lucide con trazo 1.5, todos en `<x-icon name="..." />`. No se
  mezclan sets ni se suman emojis.
- Un solo botón primario por pantalla: `btn` es el dorado y todo lo demás es
  `btn btn-ghost`. Si una vista necesita dos, una de las dos no era la acción
  principal.
- Los números de las métricas nunca van pintados: el estado se distingue por la
  etiqueta, no por el color de la cifra.