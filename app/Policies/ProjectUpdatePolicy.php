<?php

namespace App\Policies;

use App\Models\ProjectUpdate;
use App\Models\User;

class ProjectUpdatePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if (! $user->is_active) {
            return false;
        }

        return null;
    }

    /**
     * Solo se borran las notas, y solo las propias (o cualquiera, si es
     * responsable del panel).
     *
     * Los cambios de estado NO se borran nunca, ni siquiera por un admin: son
     * el registro de quien movio el proyecto y cuando, que es la razon de ser
     * del historial. Si se pudieran borrar, el panel dejaria de servir para
     * saber que paso.
     */
    public function delete(User $user, ProjectUpdate $update): bool
    {
        if ($update->isStatusChange()) {
            return false;
        }

        return $user->isAdmin() || $update->user_id === $user->id;
    }
}
