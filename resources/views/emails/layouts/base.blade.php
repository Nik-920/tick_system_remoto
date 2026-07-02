<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'INCIDEX') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background-color:#ffffff; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#111827; padding:20px 28px;">
                            <span style="color:#ffffff; font-size:18px; font-weight:bold;">{{ config('app.name', 'INCIDEX') }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 28px; border-top:1px solid #e5e7eb;">
                            <p style="margin:0 0 8px; color:#6b7280; font-size:12px;">
                                Este es un mensaje automático, no respondas a este correo.
                            </p>
                            <p style="margin:0; color:#6b7280; font-size:12px;">
                                Puedes ajustar tus preferencias de notificaciones por correo desde tu perfil.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
