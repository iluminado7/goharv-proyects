Nueva consulta desde cacciatoreabogados.com.ar
{{ $consulta->created_at->translatedFormat('d M Y, H:i') }} (hora de Argentina)

Nombre:   {{ $consulta->deQuien() }}
Empresa:  {{ $consulta->company }}
E-mail:   {{ $consulta->email }}
Teléfono: {{ $consulta->phone }}
País:     {{ $consulta->country }}

Mensaje:
{{ $consulta->message }}

Para contestarle, usá "Responder": le llega directo a {{ $consulta->email }}.
Ver en el panel: {{ route('inquiries.index') }}
