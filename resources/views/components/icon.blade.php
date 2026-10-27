{{-- Iconos Lucide, trazo 1.5. Un solo set en todo el panel: no se mezclan. --}}
@props(['name', 'size' => 22])

@php
    $paths = [
        'proyectos' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>'
                     . '<rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
        'equipo'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>'
                     . '<path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'actividad' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'perfil'    => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'salir'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>'
                     . '<path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'menu'      => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
        'plegar'    => '<rect x="3" y="3" width="18" height="18"/><path d="M9 3v18"/>',
        'sol'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/>'
                     . '<path d="M4.93 4.93l1.41 1.41"/><path d="M17.66 17.66l1.41 1.41"/><path d="M2 12h2"/>'
                     . '<path d="M20 12h2"/><path d="M6.34 17.66l-1.41 1.41"/><path d="M19.07 4.93l-1.41 1.41"/>',
        'luna'      => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9z"/>',
    ];
@endphp

<svg viewBox="0 0 24 24" width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor"
     stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
     focusable="false">{!! $paths[$name] !!}</svg>
