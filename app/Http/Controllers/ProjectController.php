<?php

namespace App\Http\Controllers;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    /** Proyectos por pagina en el tablero. */
    private const PER_PAGE = 30;

    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'status', 'priority', 'owner']);
        $sort    = $request->string('sort', 'prioridad')->toString();

        $projects = Project::with(['owner', 'links'])
            ->filtered($filters)
            ->sorted($sort)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $counts = Project::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('projects.index', [
            'projects'   => $projects,
            'counts'     => $counts,
            'archivados' => Project::onlyTrashed()->count(),
            'filters'    => $filters,
            'sort'       => $sort,
            'statuses'   => ProjectStatus::cases(),
            'priorities' => ProjectPriority::cases(),
            'members'    => $this->activeMembers(),
        ]);
    }

    /** Los archivados no aparecen en el tablero: tienen su propia pantalla. */
    public function archived(Request $request): View
    {
        $projects = Project::onlyTrashed()
            ->with(['owner', 'links'])
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->search($term))
            ->orderByDesc('deleted_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('projects.archived', [
            'projects' => $projects,
            'filters'  => $request->only('q'),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Project::class);

        return view('projects.form', [
            'project'    => new Project(['status' => ProjectStatus::Nuevo, 'priority' => ProjectPriority::Media]),
            'links'      => [],
            'statuses'   => ProjectStatus::cases(),
            'priorities' => ProjectPriority::cases(),
            'members'    => $this->activeMembers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $data = $this->validated($request);

        $project = Project::create($data);
        $project->collaborators()->sync($request->input('collaborators', []));
        $project->syncLinks($request->input('links', []));

        $project->updates()->create([
            'user_id'   => $request->user()->id,
            'body'      => 'Proyecto creado.',
            'status_to' => $project->status->value,
        ]);

        return redirect()
            ->route('projects.show', $project)
            ->with('ok', 'Proyecto creado.');
    }

    public function show(Project $project): View
    {
        $project->load(['owner', 'collaborators', 'links', 'updates.author']);

        return view('projects.show', [
            'project'  => $project,
            'statuses' => ProjectStatus::cases(),
        ]);
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.form', [
            'project'    => $project,
            'links'      => $project->links->map->only(['label', 'url'])->all(),
            'statuses'   => ProjectStatus::cases(),
            'priorities' => ProjectPriority::cases(),
            'members'    => $this->activeMembers(),
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data     = $this->validated($request);
        $newState = ProjectStatus::from($data['status']);
        unset($data['status']);

        $project->update($data);
        $project->collaborators()->sync($request->input('collaborators', []));
        $project->syncLinks($request->input('links', []));
        $project->moveTo($newState, $request->user());

        return redirect()
            ->route('projects.show', $project)
            ->with('ok', 'Cambios guardados.');
    }

    /** Atajo desde el tablero: mover de estado sin abrir el formulario. */
    public function moveStatus(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('move', $project);

        $data = $request->validate([
            'status' => ['required', Rule::in(ProjectStatus::values())],
            'note'   => ['nullable', 'string', 'max:500'],
        ]);

        $project->moveTo(ProjectStatus::from($data['status']), $request->user(), $data['note'] ?? null);

        return back()->with('ok', 'Estado actualizado.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        // Archivar tambien es un movimiento: sin esto el historial se corta sin
        // explicacion y no queda quien lo saco del tablero.
        $project->updates()->create([
            'user_id'     => $request->user()->id,
            'body'        => 'Proyecto archivado.',
            'status_from' => $project->status->value,
            'status_to'   => $project->status->value,
        ]);

        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('ok', 'Proyecto archivado. Lo podés recuperar desde Archivados.');
    }

    public function restore(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('restore', $project);

        $project->restore();

        $project->updates()->create([
            'user_id'     => $request->user()->id,
            'body'        => 'Proyecto restaurado.',
            'status_from' => $project->status->value,
            'status_to'   => $project->status->value,
        ]);

        return redirect()
            ->route('projects.show', $project)
            ->with('ok', 'Proyecto restaurado. Volvió al tablero.');
    }

    private function activeMembers()
    {
        return User::where('is_active', true)->orderBy('name')->get();
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:120'],
            'description'     => ['nullable', 'string', 'max:2000'],
            'status'          => ['required', Rule::in(ProjectStatus::values())],
            'priority'        => ['required', Rule::in(ProjectPriority::values())],
            'owner_id'        => ['nullable', 'exists:users,id'],
            'due_date'        => ['nullable', 'date'],
            'collaborators'   => ['array'],
            'collaborators.*' => ['integer', 'exists:users,id'],
            'links'           => ['array', 'max:12'],
            'links.*.label'   => ['nullable', 'string', 'max:60'],
            'links.*.url'     => ['nullable', 'url', 'max:500', 'required_with:links.*.label'],
        ], [
            'links.*.url.url'           => 'Los enlaces tienen que ser una URL completa (https://…).',
            'links.*.url.required_with' => 'Falta la URL de uno de los enlaces.',
        ]);

        unset($validated['links'], $validated['collaborators']);

        return $validated;
    }
}
