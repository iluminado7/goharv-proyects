@php ($claro = ($tema ?? 'oscuro') === 'claro')

{{-- Los dos iconos van siempre en el HTML y el CSS muestra el que toca segun
     data-theme. Asi el script cambia un atributo y el icono lo sigue solo, sin
     tener que armar un SVG a mano. --}}
<form method="POST" action="{{ route('theme.toggle') }}" class="theme-form" data-tema>
    @csrf
    <button class="icono-btn"
            title="{{ $claro ? 'Pasar a fondo negro' : 'Pasar a fondo claro' }}"
            aria-label="{{ $claro ? 'Pasar a fondo negro' : 'Pasar a fondo claro' }}">
        <span class="icono-sol"><x-icon name="sol" :size="20" /></span>
        <span class="icono-luna"><x-icon name="luna" :size="20" /></span>
    </button>
</form>
