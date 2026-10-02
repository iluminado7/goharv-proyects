@props(['user', 'size' => 32])

{{-- Si no hay foto, el circulo de iniciales. Nunca queda un hueco. --}}
@if ($user?->hasAvatar())
    <img class="avatar" src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}"
         width="{{ $size }}" height="{{ $size }}" loading="lazy"
         style="width:{{ $size }}px;height:{{ $size }}px">
@else
    <span class="avatar avatar-txt" aria-hidden="true"
          style="width:{{ $size }}px;height:{{ $size }}px;background:{{ $user?->avatarColor() ?? 'var(--line)' }};font-size:{{ max(10, (int) round($size * 0.38)) }}px">{{ $user?->initials() ?? '?' }}</span>
@endif
