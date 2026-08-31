{{-- Todo relativo a asset(): el panel funciona igual colgando de la raiz del
     dominio (Laravel Cloud) que de una subcarpeta (XAMPP). --}}
<meta name="theme-color" content="{{ ($tema ?? 'oscuro') === 'claro' ? '#FAF9F7' : '#000000' }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="GoHarv">
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
<link rel="icon" href="{{ asset('icons/icon-192.png') }}" sizes="192x192" type="image/png">
