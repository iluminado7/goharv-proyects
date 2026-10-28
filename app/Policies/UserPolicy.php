<?php

namespace App\Policies;

use App\Models\User;

/**
 * Quien puede dar de baja o reactivar una cuenta.
 *
 * La baja no borra nada: la cuenta deja de entrar al panel y desaparece de
 * todos los desplegables —responsable, colaboradores, asignar tareas—, asi que
 * no puede sumarse a ningun proyecto nuevo. Lo que hizo queda: sus proyectos,
 * sus notas, sus tareas y la bitacora siguen con su nombre.
 *
 * No hay before() con el atajo de admin a proposito: la regla de que nadie se
 * saca a si mismo tiene que valer tambien para el responsable del panel, que
 * es justamente quien podria dejarse afuera sin que quede nadie para volver a
 * entrar.
 */
class UserPolicy
{
    public function setAccess(User $actor, User $target): bool
    {
        if (! $actor->is_active || $actor->id === $target->id) {
            return false;
        }

        if ($actor->isAdmin()) {
            return true;
        }

        // El socio maneja a los profesionales externos y a nadie mas: entre
        // nosotros, dar de baja a alguien del equipo lo decide un responsable.
        return $actor->isInternal() && $target->isExternal();
    }

    /** Quien ve la lista de cuentas dadas de baja, para poder reactivarlas. */
    public function viewInactive(User $actor, User $target): bool
    {
        return $this->setAccess($actor, $target);
    }
}
