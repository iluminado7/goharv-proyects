@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Paginación">
        @if ($paginator->onFirstPage())
            <span class="pager-btn off">Anterior</span>
        @else
            <a class="pager-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a>
        @endif

        <span class="pager-at">
            Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}
            · {{ $paginator->total() }} proyectos
        </span>

        @if ($paginator->hasMorePages())
            <a class="pager-btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente</a>
        @else
            <span class="pager-btn off">Siguiente</span>
        @endif
    </nav>
@endif
