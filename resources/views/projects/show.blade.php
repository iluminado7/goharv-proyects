@extends('layouts.app')
@section('title', $project->name)

@section('content')
    @if ($project->client)
        <p class="empresa empresa-ficha">{{ $project->client }}</p>
    @endif
    <h1 class="page">{{ $project->name }}</h1>
    <p class="page-sub">
        {{ $project->owner?->name ?? 'Sin responsable' }} ·
        prioridad {{ mb_strtolower($project->priority->label()) }}
        @if ($project->due_date)
            · entrega {{ $project->due_date->translatedFormat('d \d\e F') }}
        @endif
    </p>

    <div class="card">
        @if ($project->description)
            <p style="margin:0 0 20px;max-width:66ch">{{ $project->description }}</p>
        @endif

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px">
            @foreach ($project->links as $link)
                <a class="btn {{ $loop->first ? '' : 'btn-ghost' }}" href="{{ $link->url }}"
                   target="_blank" rel="noopener" title="{{ $link->host() }}">{{ $link->label }}</a>
            @endforeach
            @can('update', $project)
                <a class="btn btn-ghost" href="{{ route('projects.edit', $project) }}">Editar</a>
            @endcan
        </div>

        @can('move', $project)
        <form method="POST" action="{{ route('projects.status', $project) }}"
              style="border-top:1px solid var(--line);padding-top:20px">
            @csrf @method('PATCH')
            <div class="row-2">
                <div class="field">
                    <label for="status">Cambiar estado</label>
                    <select id="status" name="status">
                        @foreach ($statuses as $s)
                            <option value="{{ $s->value }}" @selected($project->status === $s)>{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="note">Qué pasó</label>
                    <input id="note" name="note" placeholder="Opcional, pero ayuda al resto">
                </div>
            </div>
            <button class="btn btn-sm">Registrar movimiento</button>
        </form>
        @else
        <p class="hint" style="border-top:1px solid var(--line);padding-top:20px;margin:0">
            Estás viendo un proyecto en el que no participás. Para moverlo de estado, pedile
            al responsable que te sume como colaborador.
        </p>
        @endcan

        @can('changePriority', $project)
            <form method="POST" action="{{ route('projects.priority', $project) }}" class="prioridad-fila">
                @csrf @method('PATCH')
                <label for="priority">Prioridad</label>
                <select id="priority" name="priority">
                    @foreach ($priorities as $p)
                        <option value="{{ $p->value }}" @selected($project->priority === $p)>{{ $p->label() }}</option>
                    @endforeach
                </select>
                <button class="btn btn-ghost btn-sm">Cambiar</button>
                <span class="hint" style="margin:0"></span>
            </form>
        @endcan
    </div>


    @php ($pendientes = $project->tasks->whereNull('completed_at')->count())

    <h2 class="section">
        Tareas
        @if ($pendientes)
            <span class="pendientes-n">{{ $pendientes }} pendiente{{ $pendientes === 1 ? '' : 's' }}</span>
        @endif
    </h2>

    <div class="card">
        @forelse ($project->tasks as $task)
            <div class="tarea {{ $task->isCompleted() ? 'hecha' : '' }}">
                @can('complete', $task)
                    <form method="POST" action="{{ route('tasks.toggle', [$project, $task]) }}">
                        @csrf @method('PATCH')
                        <button class="tarea-check" aria-pressed="{{ $task->isCompleted() ? 'true' : 'false' }}"
                                aria-label="{{ $task->isCompleted() ? 'Reabrir la tarea' : 'Marcar como completada' }}"
                                title="{{ $task->isCompleted() ? 'Reabrir' : 'Marcar como completada' }}">
                            @if ($task->isCompleted())
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M5 12.5l4.5 4.5L19 7.5"/>
                                </svg>
                            @endif
                        </button>
                    </form>
                @else
                    <span class="tarea-check sin-permiso" aria-hidden="true">
                        @if ($task->isCompleted())
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                                 stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                        @endif
                    </span>
                @endcan

                <div class="tarea-cuerpo">
                    <p class="tarea-titulo">{{ $task->title }}</p>

                    <p class="tarea-pie">
                        @if ($task->due_date)
                            <span class="{{ $task->isOverdue() ? 'late' : '' }}">
                                {{ $task->isOverdue() ? 'venció' : 'para el' }}
                                {{ $task->due_date->translatedFormat('d M') }}
                            </span>
                        @else
                            <span>sin fecha</span>
                        @endif

                        @if ($task->assignees->isNotEmpty())
                            · {{ $task->assignees->pluck('name')->join(', ') }}
                        @else
                            · <span class="hint" style="margin:0">sin asignar</span>
                        @endif

                        @if ($task->isCompleted())
                            · completada {{ $task->completed_at->translatedFormat('d M') }}
                        @endif
                    </p>
                </div>

                <div class="tarea-gente">
                    @foreach ($task->assignees as $quien)
                        <x-avatar :user="$quien" :size="24" />
                    @endforeach

                    @can('delete', $task)
                        <form method="POST" action="{{ route('tasks.destroy', [$project, $task]) }}"
                              onsubmit="return confirm('¿Eliminar esta tarea? No se puede deshacer.')">
                            @csrf @method('DELETE')
                            <button class="borrar-nota">Eliminar</button>
                        </form>
                    @endcan
                </div>
            </div>
        @empty
            <p class="hint" style="margin:0">Todavía no hay tareas en este proyecto.</p>
        @endforelse
    </div>

    @can('move', $project)
        <form method="POST" action="{{ route('tasks.store', $project) }}" class="card tarea-nueva">
            @csrf

            <div class="row-2">
                <div class="field">
                    <label for="title">Qué hay que hacer</label>
                    <input id="title" name="title" maxlength="160" required
                           value="{{ old('title') }}" placeholder="Mandar la propuesta revisada">
                    @error('title') <p class="err">{{ $message }}</p> @enderror
                </div>
                <div class="field">
                    <label for="due_date">Fecha límite</label>
                    <input id="due_date" name="due_date" type="date" value="{{ old('due_date') }}">
                    @error('due_date') <p class="err">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="field">
                <label>Para quién</label>
                <p class="hint">Solo gente del proyecto. Sumá colaboradores desde <em>Editar</em> si falta alguien.</p>

                <div class="picker">
                    @foreach ($candidatos as $quien)
                        <label class="pick">
                            <input type="checkbox" name="assignees[]" value="{{ $quien->id }}"
                                   @checked(in_array($quien->id, old('assignees', [])))>
                            <span>{{ $quien->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="form-acts">
                <button class="btn btn-sm">Agregar tarea</button>
            </div>
        </form>
    @endcan

    <h2 class="section">Notas</h2>

    @can('comment', $project)
        <form method="POST" action="{{ route('projects.comment', $project) }}" class="comment-box">
            @csrf
            <label for="body" class="sr-only">Comentario</label>
            <textarea id="body" name="body" rows="2" maxlength="1000"
                      placeholder="Dejá una nota: qué falta, qué trabaste, qué averiguaste. No mueve el estado.">{{ old('body') }}</textarea>
            @error('body') <p class="err">{{ $message }}</p> @enderror
            <div class="comment-acts">
                <button class="btn btn-sm">Comentar</button>
            </div>
        </form>
    @endcan

    <ul class="timeline">
        @forelse ($project->updates as $u)
            <li class="{{ $u->isStatusChange() ? '' : 'is-note' }}">
                {{-- El cuerpo va en su propio elemento con white-space:pre-line,
                     para respetar los saltos de linea sin dejar pasar HTML. --}}
                <p class="cuerpo">@if ($u->isStatusChange())<strong>{{ $u->status_from?->label() ?? 'Alta' }} → {{ $u->status_to?->label() }}</strong> @endif{{ $u->body }}</p>
                <div class="pie">
                    <span class="when">
                        <x-avatar :user="$u->author" :size="20" />
                        {{ $u->author?->name ?? 'Alguien' }} · {{ $u->created_at->translatedFormat('d M Y, H:i') }}
                    </span>
                    @can('delete', $u)
                        <form method="POST" action="{{ route('projects.comment.destroy', [$project, $u]) }}"
                              onsubmit="return confirm('¿Borrar esta nota? No se puede deshacer.')">
                            @csrf @method('DELETE')
                            <button class="borrar-nota">Borrar</button>
                        </form>
                    @endcan
                </div>
            </li>
        @empty
            <li><p style="margin:0;color:var(--muted)">Todavía no hay movimientos ni comentarios.</p></li>
        @endforelse
    </ul>
    <div style="height:60px"></div>
@endsection
