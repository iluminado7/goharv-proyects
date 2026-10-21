<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if (! $user->is_active) {
            return false;
        }

        return $user->isAdmin() ? true : null;
    }

    /**
     * Completar o volver atrás lo puede hacer cualquiera del proyecto, no solo
     * a quien se le asignó: es una lista compartida, y el que avisa "esto ya
     * está" suele ser otro. Se puede deshacer, así que el costo de equivocarse
     * es un clic.
     */
    public function complete(User $user, Task $task): bool
    {
        return $this->enElProyecto($user, $task);
    }

    /**
     * Editar cambia lo que otro tiene que hacer —el texto, la fecha, a quién
     * le toca—, así que no es lo mismo que tildar un casillero.
     */
    public function update(User $user, Task $task): bool
    {
        return $this->decide($user, $task);
    }

    /**
     * Borrar no se deshace, así que es más cerrado: quien la creó —se subió
     * por error— o el responsable del proyecto, que es quien decide si algo
     * deja de hacer falta.
     */
    public function delete(User $user, Task $task): bool
    {
        return $this->decide($user, $task);
    }

    /** Quien la pidio, o quien manda en el proyecto. */
    private function decide(User $user, Task $task): bool
    {
        return $task->created_by === $user->id
            || $task->project->owner_id === $user->id;
    }

    private function enElProyecto(User $user, Task $task): bool
    {
        return $task->project->interesados()->contains('id', $user->id);
    }
}
