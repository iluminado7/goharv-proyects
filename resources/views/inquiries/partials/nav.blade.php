{{-- Lista de formularios de Consultas web, agrupada (la usan la barra lateral y el desplegable del celular). --}}
<nav class="inq-nav" aria-label="{{ $navLabel }}">
    @foreach ($groups as $group => $entries)
        <p class="inq-group">{{ $group }}</p>
        <ul>
            @foreach ($entries as $entry)
                @php ($activo = $form === $entry['key'])
                <li>
                    <a class="inq-link {{ $activo ? 'on' : '' }} {{ $entry['count'] ? '' : 'empty-count' }}"
                       href="{{ $link($entry['key']) }}"
                       @if ($activo) aria-current="page" @endif>
                        <span class="inq-link-l">{{ $entry['label'] }}</span>
                        <span class="inq-count">{{ $entry['count'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endforeach
</nav>
