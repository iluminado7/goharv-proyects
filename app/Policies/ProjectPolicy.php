<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Regla del equipo: el proyecto lo edita quien esta metido en el proyecto.
 * El resto lo ve, pero no lo toca. Archivar y restaurar quedan para el
 * responsable del proyecto y los responsables del panel.
 */
class ProjectPolicy
{
    /** Los responsables del panel pasan por arriba de todo. */
    public function before(User $user, string $ability): ?bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Project $project): bool
    {
        return $this->involved($user, $project);
    }

    /** Mover de estado: lo mismo que editar. */
    public function move(User $user, Project $project): bool
    {
        return $this->involved($user, $project);
    }

    /**
     * Comentar es mas abierto que editar a proposito: si alguien del equipo ve
     * algo que sirve, tiene que poder decirlo sin que lo sumen al proyecto.
     * No cambia ningun dato, solo agrega una linea al historial.
     */
    public function comment(User $user, Project $project): bool
    {
        return true;
    }

    public function delete(User $user, Project $project): bool
    {
        return $project->owner_id === $user->id;
    }

    public function restore(User $user, Project $project): bool
    {
        return $project->owner_id === $user->id;
    }

    private function involved(User $user, Project $project): bool
    {
        if ($project->owner_id === $user->id) {
            return true;
        }

        return $project->relationLoaded('collaborators')
            ? $project->collaborators->contains('id', $user->id)
            : $project->collaborators()->whereKey($user->id)->exists();
    }
}
