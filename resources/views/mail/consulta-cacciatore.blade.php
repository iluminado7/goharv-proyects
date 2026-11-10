{{--
    Mail al estudio por cada consulta de cacciatoreabogados.com.ar. Usa la base
    de los correos del panel (mail.layout): tablas y estilos en linea.

    Todo lo que escribio el visitante va con {{ }}: Blade lo escapa y no se
    puede colar HTML en el mail.
--}}
@php
    $cuerpo = 'Roboto,Helvetica,Arial,sans-serif';
    $filas = [
        'Nombre'   => $consulta->deQuien(),
        'Empresa'  => $consulta->company,
        'E-mail'   => $consulta->email,
        'Teléfono' => $consulta->phone,
        'País'     => $consulta->country,
    ];
@endphp
@component('mail.layout', [
    'titulo'   => 'Nueva consulta desde la web',
    'etiqueta' => 'cacciatoreabogados.com.ar',
    'enlace'   => route('inquiries.index'),
    'accion'   => 'Ver en el panel',
])
    <p style="margin:0 0 18px;font-family:{{ $cuerpo }};font-size:13px;line-height:1.6;color:#555555;">
        {{ $consulta->created_at->translatedFormat('d M Y, H:i') }} (hora de Argentina)
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 18px;">
        @foreach ($filas as $label => $valor)
            <tr>
                <td style="padding:6px 12px 6px 0;width:96px;vertical-align:top;font-family:{{ $cuerpo }};font-size:14px;color:#555555;">{{ $label }}</td>
                <td style="padding:6px 0;font-family:{{ $cuerpo }};font-size:14px;color:#000000;">{{ $valor }}</td>
            </tr>
        @endforeach
    </table>

    {{-- pre-line: respeta los saltos de linea sin imprimir HTML crudo --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="margin:0 0 18px;border-left:3px solid #BFAE76;background:#F5F5F5;">
        <tr>
            <td style="padding:14px 16px;font-family:{{ $cuerpo }};font-size:15px;line-height:1.6;color:#000000;white-space:pre-line;">{{ $consulta->message }}</td>
        </tr>
    </table>

    <p style="margin:0;font-family:{{ $cuerpo }};font-size:14px;line-height:1.6;color:#555555;">
        Para contestarle, usá <strong style="color:#000000;">Responder</strong>: le llega directo a {{ $consulta->email }}.
    </p>
@endcomponent
