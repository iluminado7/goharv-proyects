@component('mail.layout', ['titulo' => $titulo, 'etiqueta' => 'Aviso', 'enlace' => $enlace, 'accion' => $accion])
    <p style="margin:0 0 14px;font-family:Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#555555;">
        Hola {{ $nombre }},
    </p>

    <p style="margin:0 0 14px;font-family:Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#555555;">
        @if ($actor)
            <strong style="color:#000000;">{{ $actor }}</strong>
        @else
            Alguien
        @endif
        {{ $frase }}
        <strong style="color:#000000;">{{ $proyecto }}</strong>.
    </p>

    @if ($detalle)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="margin:0 0 4px;border-left:3px solid #BFAE76;background:#F5F5F5;">
            <tr>
                <td style="padding:14px 16px;font-family:Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#000000;">
                    {{ $detalle }}
                </td>
            </tr>
        </table>
    @endif
@endcomponent
