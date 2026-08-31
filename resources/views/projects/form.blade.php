@extends('layouts.app')
@section('title', $project->exists ? 'Editar proyecto' : 'Nuevo proyecto')

@section('content')
    <h1 class="page">{{ $project->exists ? $project->name : 'Nuevo proyecto' }}</h1>
    <p class="page-sub">{{ $project->exists ? 'Los cambios de estado quedan registrados en el historial.' : 'Cargá lo mínimo para no perderlo de vista: nombre, enlace y prioridad.' }}</p>

    <form method="POST"
          action="{{ $project->exists ? route('projects.update', $project) : route('projects.store') }}"
          class="card">
        @csrf
        @if ($project->exists) @method('PUT') @endif

        <div class="field">
            <label for="name">Nombre</label>
            <input id="name" name="name" value="{{ old('name', $project->name) }}" required>
            @error('name') <p class="err">{{ $message }}</p> @enderror
        </div>

        @php
            // Los cargados + tres filas vacias para sumar sin recargar la pagina.
            $rows = old('links', $links);
            $rows = array_values($rows);
            $rows = array_merge($rows, array_fill(0, 3, ['label' => '', 'url' => '']));
        @endphp

        <div class="field">
            <label>Enlaces</label>
            <p class="hint">Repo, Drive, staging, diseño. El primero de la lista es el que abre el botón del tablero.</p>

            <div class="links-grid">
                @foreach ($rows as $i => $row)
                    <input name="links[{{ $i }}][label]" value="{{ $row['label'] ?? '' }}"
                           placeholder="{{ $i === 0 ? 'Principal' : 'Nombre' }}" maxlength="60" aria-label="Nombre del enlace {{ $i + 1 }}">
                    <input name="links[{{ $i }}][url]" type="url" value="{{ $row['url'] ?? '' }}"
                           placeholder="https://…" aria-label="URL del enlace {{ $i + 1 }}">
                    @error('links.'.$i.'.url') <p class="err span-2">{{ $message }}</p> @enderror
                @endforeach
            </div>
            <p class="hint">Para borrar un enlace, vaciá su URL y guardá.</p>
        </div>

        <div class="field">
            <label for="description">Detalle</label>
            <textarea id="description" name="description"
                      placeholder="En una o dos líneas: de qué se trata y qué falta">{{ old('description', $project->description) }}</textarea>
            @error('description') <p class="err">{{ $message }}</p> @enderror
        </div>

        <div class="row-2">
            <div class="field">
                <label for="status">Estado</label>
                <select id="status" name="status">
                    @foreach ($statuses as $s)
                        <option value="{{ $s->value }}" @selected(old('status', $project->status?->value) === $s->value)>{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="priority">Prioridad</label>
                <select id="priority" name="priority">
                    @foreach ($priorities as $p)
                        <option value="{{ $p->value }}" @selected(old('priority', $project->priority?->value) === $p->value)>{{ $p->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="row-2">
            <div class="field">
                <label for="owner_id">Responsable</label>
                <select id="owner_id" name="owner_id">
                    <option value="">Sin asignar</option>
                    @foreach ($members as $m)
                        <option value="{{ $m->id }}" @selected(old('owner_id', $project->owner_id) == $m->id)>{{ $m->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="due_date">Fecha de entrega</label>
                <input id="due_date" name="due_date" type="date"
                       value="{{ old('due_date', $project->due_date?->format('Y-m-d')) }}">
            </div>
        </div>

        <div class="field">
            <label>Quiénes más lo tocan</label>
            <p class="hint">Además del responsable. Los marcados pueden editarlo y moverlo de estado.</p>

            @php
                // Sin checkboxes marcados el navegador no manda la clave: si venimos
                // de un error de validacion vale la lista vacia, no la guardada.
                $marcados = session()->hasOldInput()
                    ? old('collaborators', [])
                    : ($project->exists ? $project->collaborators->pluck('id')->all() : []);
            @endphp

            {{-- Checkboxes y no un <select multiple>: ese pide Ctrl+clic y nadie lo adivina. --}}
            <div class="picker">
                @foreach ($members as $m)
                    <label class="pick">
                        <input type="checkbox" name="collaborators[]" value="{{ $m->id }}"
                               @checked(in_array($m->id, $marcados))>
                        <span>{{ $m->name }}</span>
                    </label>
                @endforeach
            </div>
            @error('collaborators.*') <p class="err">{{ $message }}</p> @enderror
        </div>

        <div class="form-acts">
            @if ($project->exists && auth()->user()->can('delete', $project))
                <button form="archivar" class="btn btn-danger btn-sm left"
                        onclick="return confirm('¿Archivar este proyecto? Sale del tablero, pero se recupera desde Archivados.')">Archivar</button>
            @endif
            <a class="btn btn-ghost" href="{{ route('projects.index') }}">Cancelar</a>
            <button class="btn">Guardar</button>
        </div>
    </form>

    @if ($project->exists)
        <form id="archivar" method="POST" action="{{ route('projects.destroy', $project) }}">
            @csrf @method('DELETE')
        </form>
    @endif
@endsection
