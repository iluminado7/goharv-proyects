@component('mail.layout', ['titulo' => 'Tu cuenta ya está lista', 'etiqueta' => 'Bienvenida', 'enlace' => $enlace, 'accion' => 'Entrar al panel'])
    <p style="margin:0 0 14px;font-family:Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#555555;">
        Hola {{ $nombre }},
    </p>

    <p style="margin:0 0 20px;font-family:Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#555555;">
        {{ $quien }} te creó una cuenta en el panel de proyectos de GoHarv. Entrás con
        estos datos:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="border:1px solid #E2E2E2;border-left:3px solid #BFAE76;">
        <tr>
            <td style="padding:16px 18px;font-family:Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.9;color:#000000;">
                <span style="font-family:'Montserrat',Helvetica,Arial,sans-serif;font-size:10px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:#8A7538;">Usuario</span><br>
                {{ $correo }}
                <br><br>
                <span style="font-family:'Montserrat',Helvetica,Arial,sans-serif;font-size:10px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:#8A7538;">Clave provisoria</span><br>
                <span style="font-family:'Courier New',Courier,monospace;font-size:16px;">{{ $clave }}</span>
            </td>
        </tr>
    </table>

    <p style="margin:20px 0 0;font-family:Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#555555;">
        <strong style="color:#000000;">Esta clave es de un solo uso.</strong> Apenas entres, el
        panel te va a pedir que la cambies por una tuya, y desde ese momento la de este
        correo deja de servir. Después podés borrar este mensaje.
    </p>

    <p style="margin:14px 0 0;font-family:Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#555555;">
        Tu rol es <strong style="color:#000000;">{{ $rol }}</strong>.
    </p>
@endcomponent
