{{--
    Base de todos los correos del panel.

    Va con tablas y estilos en linea a proposito: Gmail, Outlook y compania
    descartan las hojas de estilo externas y buena parte del CSS moderno, asi
    que aca no sirve goharv.css. Se respetan igual las reglas de la marca —
    dorado como unico acento, esquinas rectas, sin sombras ni degrades— pero
    escritas a mano. Montserrat no carga en la mayoria de los clientes: queda
    como primera opcion y abajo las de sistema.
--}}
@php
    $dorado  = '#BFAE76';
    $oscuro  = '#8A7538';
    $borde   = '#E2E2E2';
    $texto   = '#000000';
    $gris    = '#555555';
    $titulos = "'Montserrat',Helvetica,Arial,sans-serif";
    $cuerpo  = "Roboto,Helvetica,Arial,sans-serif";
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $titulo }}</title>
</head>
<body style="margin:0;padding:0;background:#F5F5F5;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="background:#F5F5F5;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
                       style="width:600px;max-width:100%;background:#FFFFFF;border:1px solid {{ $borde }};">

                    <tr>
                        <td style="padding:26px 32px;border-bottom:1px solid {{ $dorado }};">
                            <div style="font-family:{{ $titulos }};font-size:21px;font-weight:700;letter-spacing:.02em;color:{{ $texto }};">
                                GoHarv.
                            </div>
                            <div style="font-family:{{ $titulos }};font-size:10px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:{{ $oscuro }};padding-top:4px;">
                                Project Manager
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:32px;">
                            <div style="font-family:{{ $titulos }};font-size:11px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:{{ $oscuro }};padding-bottom:10px;">
                                {{ $etiqueta }}
                            </div>

                            <h1 style="margin:0 0 18px;font-family:{{ $titulos }};font-size:25px;font-weight:700;line-height:1.2;color:{{ $texto }};">
                                {{ $titulo }}
                            </h1>

                            {{ $slot }}

                            @isset($enlace)
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:28px;">
                                    <tr>
                                        <td style="background:{{ $dorado }};">
                                            <a href="{{ $enlace }}"
                                               style="display:inline-block;padding:14px 30px;font-family:{{ $titulos }};font-size:13px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#000000;text-decoration:none;">
                                                {{ $accion }}
                                            </a>
                                        </td>
                                    </tr>
                                </table>

                                {{-- Algunos clientes no dejan tocar los botones: la URL va escrita. --}}
                                <p style="margin:18px 0 0;font-family:{{ $cuerpo }};font-size:12px;line-height:1.6;color:{{ $gris }};">
                                    Si el botón no anda, copiá esta dirección en el navegador:<br>
                                    <span style="color:{{ $oscuro }};word-break:break-all;">{{ $enlace }}</span>
                                </p>
                            @endisset
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 32px;border-top:1px solid {{ $dorado }};">
                            <div style="font-family:{{ $titulos }};font-size:10px;font-weight:600;letter-spacing:.14em;text-transform:uppercase;color:{{ $oscuro }};">
                                GoHarv Project Manager · {{ now()->year }}
                            </div>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
