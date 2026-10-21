<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\NotificationType;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ProjectEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Las tareas viven siempre dentro de un proyecto: todas las rutas cuelgan de
 * el y usan scopeBindings, asi que una tarea de otro proyecto da 404.
 */
class TaskController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        // Crear una tarea es trabajo del proyecto: lo mismo que moverlo.
        $this->authorize('move', $project);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:500'],
            'due_date'    => ['nullable', 'date'],
            'assignees'   => ['array'],
            'assignees.*' => ['integer', 'exists:users,id'],
        ], [
            'title.required' => 'Escribí qué hay que hacer.',
            'title.max'      => 'El título no puede pasar de 500 caracteres.',
        ]);

        $task = $project->tasks()->create([
            'title'      => $data['title'],
            'due_date'   => $data['due_date'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        // assignTo descarta a quien no participa del proyecto.
        $task->assignTo($data['assignees'] ?? []);

        Activity::anotar(ActivityAction::TareaCreada, $request->user(), $project, detail: $task->title);

        // Se avisa a quienes quedaron a cargo, menos a quien la creó: una
        // tarea con fecha límite que nadie sabe que tiene no sirve de nada.
        $this->avisar(
            $task->assignees,
            $request->user(),
            NotificationType::TareaAsignada,
            $project,
            $task->title,
        );

        return back()->with('ok', 'Tarea agregada.');
    }

    /**
     * Cambiar el texto, la fecha o a quién le toca. Antes la única salida era
     * eliminar y volver a crear, perdiendo quién la pidió y cuándo.
     */
    public function update(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:500'],
            'due_date'    => ['nullable', 'date'],
            'assignees'   => ['array'],
            'assignees.*' => ['integer', 'exists:users,id'],
        ], [
            'title.required' => 'La tarea necesita un título.',
            'title.max'      => 'El título no puede pasar de 500 caracteres.',
        ]);

        $antes = $task->assignees->pluck('id');

        $task->update([
            'title'    => $data['title'],
            'due_date' => $data['due_date'] ?? null,
        ]);

        $task->assignTo($data['assignees'] ?? []);

        Activity::anotar(ActivityAction::TareaEditada, $request->user(), $project, detail: $task->title);

        // Solo a los que no estaban antes: guardar sin tocar la lista no tiene
        // por qué volver a avisarle a nadie.
        $this->avisar(
            $task->load('assignees')->assignees->reject(fn (User $u) => $antes->contains($u->id)),
            $request->user(),
            NotificationType::TareaAsignada,
            $project,
            $task->title,
        );

        return back()->with('ok', 'Tarea actualizada.');
    }

    /** Marca o desmarca. El mismo boton sirve para los dos sentidos. */
    public function toggle(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('complete', $task);

        $completada = ! $task->isCompleted();

        // toggle() devuelve false si el estado ya era ese: sin cambio real no
        // se anota nada ni se avisa a nadie.
        if (! $task->toggle($completada, $request->user())) {
            return back();
        }

        Activity::anotar(
            $completada ? ActivityAction::TareaCompletada : ActivityAction::TareaReabierta,
            $request->user(), $project, detail: $task->title,
        );

        if ($completada) {
            // A los demás asignados y a quien la creó: son los que estaban
            // esperando que se hiciera. Reabrir no avisa: pasa poco y quien
            // lo hace suele ser el mismo que la había completado.
            $this->avisar(
                $task->assignees->merge([$task->creator])->filter(),
                $request->user(),
                NotificationType::TareaHecha,
                $project,
                $task->title,
            );
        }

        return back()->with('ok', $completada ? 'Tarea completada.' : 'Tarea reabierta.');
    }

    public function destroy(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        // Antes de borrar: después el título ya no existe y es justo el dato
        // que hace falta para entender el registro.
        Activity::anotar(ActivityAction::TareaEliminada, $request->user(), $project, detail: $task->title);

        $task->delete();

        return back()->with('ok', 'Tarea eliminada.');
    }

    /** Nadie recibe aviso de lo suyo, ni las cuentas dadas de baja. */
    private function avisar(
        Collection $gente,
        User $actor,
        NotificationType $tipo,
        Project $project,
        string $detalle,
    ): void {
        $destinatarios = $gente
            ->unique('id')
            ->reject(fn (User $u) => $u->id === $actor->id || ! $u->is_active);

        Notification::send($destinatarios, new ProjectEvent($tipo, $project, $actor, $detalle));
    }
}
