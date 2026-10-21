<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
            'title'       => ['required', 'string', 'max:160'],
            'due_date'    => ['nullable', 'date'],
            'assignees'   => ['array'],
            'assignees.*' => ['integer', 'exists:users,id'],
        ], [
            'title.required' => 'Escribí qué hay que hacer.',
            'title.max'      => 'El título no puede pasar de 160 caracteres.',
        ]);

        $task = $project->tasks()->create([
            'title'      => $data['title'],
            'due_date'   => $data['due_date'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        // assignTo descarta a quien no participa del proyecto.
        $task->assignTo($data['assignees'] ?? []);

        return back()->with('ok', 'Tarea agregada.');
    }

    /** Marca o desmarca. El mismo boton sirve para los dos sentidos. */
    public function toggle(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('complete', $task);

        $completada = ! $task->isCompleted();

        $task->toggle($completada, $request->user());

        return back()->with('ok', $completada ? 'Tarea completada.' : 'Tarea reabierta.');
    }

    public function destroy(Project $project, Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return back()->with('ok', 'Tarea eliminada.');
    }
}
