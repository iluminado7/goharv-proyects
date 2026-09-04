<article class="item">
    <div class="pbar" style="background:{{ $project->priority->color() }}"></div>

    <div class="body">
        @if ($project->client)
            <p class="empresa">{{ $project->client }}</p>
        @endif
        <div class="title-line">
            <h3><a href="{{ route('projects.show', $project) }}" class="to-detail">{{ $project->name }}</a></h3>
            <span class="prio">prioridad {{ mb_strtolower($project->priority->label()) }}</span>
        </div>

        @if ($project->description)
            <p class="desc">{{ Str::limit($project->description, 160) }}</p>
        @endif

        <p class="meta">
            {{ $project->owner?->name ?? 'Sin responsable' }} ·
            actualizado {{ $project->updated_at->translatedFormat('d M Y') }}
            @if ($project->due_date)
                · <span class="{{ $project->isOverdue() ? 'late' : '' }}">
                    entrega {{ $project->due_date->translatedFormat('d M') }}
                  </span>
            @endif
        </p>
    </div>

    <div class="track">
        <div class="track-line">
            @for ($i = 1; $i <= 4; $i++)
                <span @style(['background:'.$project->status->color() => $i <= $project->status->step()])></span>
            @endfor
        </div>
        <div class="track-label">
            <span class="dot" style="background:{{ $project->status->color() }}"></span>
            {{ $project->status->label() }}
        </div>
    </div>

    <div class="acts">
        @php ($link = $project->primaryLink())
        @if ($link)
            <a class="btn btn-sm" href="{{ $link->url }}" target="_blank" rel="noopener">URL</a>
            @if ($project->links->count() > 1)
                <span class="more-links">+{{ $project->links->count() - 1 }}</span>
            @endif
        @endif
        <a class="btn btn-ghost btn-sm" href="{{ route('projects.show', $project) }}">Notas</a>
        @can('update', $project)
            <a class="btn btn-ghost btn-sm" href="{{ route('projects.edit', $project) }}">Editar</a>
        @endcan
    </div>
</article>
