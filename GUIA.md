# GoHarv — Guía del panel

Esto es una guía de uso para el equipo. Si buscás cómo instalarlo o cómo está
hecho por dentro, eso está en el [README](README.md).

---

## Para qué sirve

El panel es el lugar donde viven los proyectos del equipo. Reemplaza el
seguimiento repartido entre chats, planillas y la memoria de cada uno.

La idea es simple: **cada proyecto tiene un estado, un responsable y un
historial**. En cualquier momento se puede entrar y ver en qué anda cada cosa y
quién movió qué. Nada se pierde: hasta archivar un proyecto queda registrado.

---

## Entrar

Se entra con tu correo y tu clave. La sesión queda abierta si marcás *Mantener
la sesión abierta*.

Si te equivocás cinco veces seguidas, el panel te frena un minuto. No es una
falla: es para que nadie pueda probar claves a fuerza bruta.

**Si olvidaste la clave**, por ahora no hay recuperación automática por correo.
Pedile a un responsable del panel que te la resetee. Cambiala apenas entres,
desde *Mi perfil*.

### Instalarlo en el celular

El panel se puede instalar como una app, con su ícono propio y sin la barra del
navegador.

- **Android:** entrá desde Chrome y aceptá el aviso de instalar que aparece
  solo. Si no aparece, está en el menú de tres puntos → *Instalar app*.
- **iPhone:** entrá desde Safari, tocá el botón de compartir y elegí *Agregar a
  inicio*. Apple no ofrece el aviso automático, hay que hacerlo a mano.

Necesita internet para funcionar: el panel lee los proyectos del servidor. Sin
señal vas a ver una pantalla que te avisa, en vez del error del navegador.

---

## El tablero

Es la pantalla principal. Arriba de todo, los números: cuántos proyectos hay y
cuántos en cada estado. **Son botones**: clic en *en desarrollo* y el listado
queda solo con esos; clic en *proyectos* y vuelven todos.

### Encontrar algo

- **Buscador:** busca por nombre, por el detalle y por la empresa. No busca
  dentro de las notas.
- **Todas las empresas:** filtra por cliente. Aparece cuando hay al menos un
  proyecto con empresa cargada.
- **Todo el equipo:** filtra por responsable. Muestra los proyectos donde esa
  persona figura como responsable, no en los que colabora.
- **Ordenar:** por prioridad (el orden por defecto), por estado, por últimos
  movimientos o alfabético.
- **Los números de arriba:** filtran por estado. Se combinan con el resto, así
  que podés pedir "los proyectos en desarrollo de tal empresa".

Con muchos proyectos el listado se corta de a 30 y aparecen *Anterior* y
*Siguiente* abajo. Los filtros se mantienen al cambiar de página.

### Leer una fila

Cada proyecto muestra:

- **La barra de color de la izquierda:** la prioridad. Roja es alta, amarilla
  media, gris baja.
- **La barra de cuatro tramos de la derecha:** en qué punto del recorrido está.
- **La fecha de entrega**, si tiene. Aparece en rojo si ya venció y el proyecto
  todavía no está terminado.

Y tres botones:

| Botón | A dónde va |
|---|---|
| **URL** | Abre el enlace del proyecto en otra pestaña |
| **Notas** | La ficha del proyecto: historial, comentarios y cambio de estado |
| **Editar** | El formulario para cambiar los datos |

Si un proyecto tiene más de un enlace, al lado de *URL* aparece un `+2` que te
dice cuántos más hay. Están todos en la ficha.

---

## Un proyecto por dentro

Entrás con **Notas** o clickeando el nombre del proyecto.

Arriba están los enlaces y el botón de editar. Abajo, dos cosas distintas que
conviene no confundir:

**Cambiar estado.** Movés el proyecto de un estado al siguiente. El campo *Qué
pasó* es opcional, pero es lo que después le explica al resto por qué se movió.
Usalo.

**Notas.** Un comentario que **no mueve el proyecto**. Sirve para dejar algo
dicho: qué estás esperando, con quién hablaste, qué trabó. Cualquiera del equipo
puede comentar en cualquier proyecto, aunque no participe.

### El historial

Abajo de todo está la línea de tiempo, de lo más nuevo a lo más viejo. Cada
línea dice quién y cuándo.

- **Punto lleno:** un movimiento de estado.
- **Punto hueco:** una nota.

Ahí queda todo: el alta del proyecto, cada cambio de estado, cada comentario, y
también cuándo se archivó o se restauró.

---

## Cargar un proyecto

Con **Agregar proyecto**, arriba a la derecha del tablero.

Lo único obligatorio es el **nombre**. Todo lo demás se puede completar después,
así que no lo dejes sin cargar por no tener todos los datos.

**Empresa.** Para qué cliente es el proyecto. Al escribir, el panel te sugiere
las empresas que ya cargaste: **elegí siempre la sugerencia si la empresa ya
existe**. Si la escribís distinto —"Cerrajería Leonardo" y "cerrajeria leonardo"—
el panel las va a tratar como dos empresas separadas y el filtro te va a mostrar
los proyectos partidos en dos.

Una vez cargada, aparece arriba del nombre en el tablero y en la ficha, y podés
filtrar el tablero por empresa desde el desplegable de arriba.

**Enlaces.** Podés poner todos los que necesite: el repo, la carpeta de Drive, el
sitio de prueba, el diseño. Cada uno lleva un nombre y una URL. Poné nombres
cortos y claros —"Repo", "Drive", "Diseño"— porque eso es lo que se ve en el
botón. Si dejás el nombre vacío, el panel inventa uno a partir de la dirección,
pero queda feo.

El **primero de la lista** es el que abre el botón *URL* del tablero, así que
poné ahí el que más usás. Para borrar un enlace, vaciá su dirección y guardá.

**Responsable.** Quien se hace cargo. Puede archivar el proyecto y es a quien
le van a preguntar.

**Quiénes más lo tocan.** Los colaboradores. Marcalos con los cuadraditos.
Pueden editar y mover el proyecto, pero no archivarlo.

**Fecha de entrega.** Opcional. Si la ponés, el panel te avisa en rojo cuando se
pasa.

---

## Estados y prioridades

Los cuatro estados van en orden:

**Nuevo** → **Inicio** → **En desarrollo** → **Terminado**

No hay una regla que te impida saltear pasos o volver atrás. Si un proyecto
terminado se reabre, movelo para atrás y dejá dicho por qué en *Qué pasó*.

La **prioridad** (alta, media, baja) no cambia nada del funcionamiento: cambia el
orden en que aparecen. Con el orden por defecto, primero salen las altas, y
dentro de cada grupo, las que tienen entrega más cercana.

---

## Archivar, restaurar y borrar

**Archivar** saca el proyecto del tablero sin perder nada. Está al final del
formulario de *Editar*, abajo a la izquierda. Es lo que hay que usar cuando un
proyecto se terminó o se cayó: deja de estorbar en el listado pero queda entero.

Los archivados están en el botón **Archivados**, a la derecha de los filtros del
tablero, con el número al lado. Ahí se pueden buscar y **restaurar** con un
clic: vuelven al tablero como estaban, con su historial completo.

**Borrar para siempre** es otra cosa. Solo pueden los responsables del panel,
solo sobre proyectos ya archivados, y hay que escribir el nombre del proyecto
para confirmar. Se lleva el historial, los enlaces y los colaboradores, y no se
puede deshacer.

En la práctica, casi nunca hace falta: si lo que querés es sacarlo de la vista,
archivarlo alcanza.

---

## Quién puede qué

Hay dos tipos de cuenta.

**Responsable del panel.** Administra el equipo: suma gente, cambia permisos y da
de baja cuentas. Además puede editar, mover, archivar y borrar cualquier
proyecto, participe o no.

**Miembro.** Ve todo el tablero, pero solo modifica los proyectos donde está
metido.

Para un miembro, lo que puede hacer depende de su relación con cada proyecto:

| | Es el responsable | Es colaborador | No participa |
|---|---|---|---|
| Ver la ficha y el historial | Sí | Sí | Sí |
| Dejar notas | Sí | Sí | Sí |
| Editar datos y enlaces | Sí | Sí | No |
| Cambiar el estado | Sí | Sí | No |
| Archivar y restaurar | Sí | No | No |
| Borrar para siempre | No | No | No |

**Crear proyectos lo puede hacer cualquiera.**

Dos detalles: todos pueden **ver** todo —el panel no esconde proyectos— y todos
pueden **comentar** en cualquier proyecto, porque un comentario no cambia nada y
sirve para avisar algo sin tener que pedir que te sumen.

---

## Tu cuenta

En **Mi perfil** cambiás tu nombre, tu correo y tu clave. Ahí también ves qué
rol tenés y cuántos proyectos tenés a cargo.

Para cambiar la clave hay que escribir la actual. Mínimo 8 caracteres. El ojito
al costado del campo te deja ver lo que estás escribiendo, que en el celular
ayuda bastante.

Tu rol y el alta o baja de tu cuenta los maneja un responsable del panel, no vos.

---

## El equipo

Solo para responsables del panel, en **Equipo**.

Para **sumar a alguien** hace falta nombre, correo, una clave inicial y el nivel
de permisos. Pasale la clave inicial por un medio seguro y decile que la cambie
apenas entre.

Para **cambiar permisos o dar de baja**, los dos desplegables de cada fila y
*Guardar*. Dar de baja no borra a la persona ni sus proyectos: solo la deja
afuera del panel. Se puede reactivar cuando quieras.

El panel no te va a dejar dar de baja al último responsable activo. Si lo
intentás, te lo dice.

---

## El fondo claro y oscuro

El botón ☀ / ☾ del menú alterna entre fondo negro y claro. La elección se
guarda en tu navegador, así que cada uno lo deja como prefiere sin afectar al
resto.

---

## Lo que todavía no hace

- **Recuperar la clave por correo.** Hoy hay que pedirle a un responsable que la
  resetee a mano.
- **Avisos.** El panel no notifica nada: no manda correo cuando te asignan un
  proyecto ni cuando se vence una entrega. Hay que entrar y mirar.
- **Adjuntar archivos.** Por ahora los archivos van por enlace, a Drive o donde
  los tengan.
- **Exportar.** No hay forma de bajarse un listado para un informe.
- **Las horas están tres horas adelantadas.** El historial muestra la hora del
  servidor, no la de Argentina. Las fechas están bien; los horarios, corridos.
  Está pendiente de arreglar.
