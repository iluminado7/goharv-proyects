@php ($claro = ($tema ?? 'oscuro') === 'claro')

<form method="POST" action="{{ route('theme.toggle') }}" class="theme-form">
    @csrf
    <button class="btn btn-ghost btn-sm theme-btn"
            title="{{ $claro ? 'Pasar a fondo negro' : 'Pasar a fondo claro' }}"
            aria-label="{{ $claro ? 'Pasar a fondo negro' : 'Pasar a fondo claro' }}">
        <span aria-hidden="true">{{ $claro ? '☾' : '☀' }}</span>
    </button>
</form>
