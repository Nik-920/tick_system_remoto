@extends('emails.layouts.base')

@section('content')
    <h1 style="margin:0 0 16px; color:#111827; font-size:18px;">Restablece tu contraseña</h1>

    <p style="margin:0 0 12px; color:#374151; font-size:14px; line-height:1.5;">
        Recibimos una solicitud para restablecer la contraseña de tu cuenta en {{ config('app.name', 'INCIDEX') }}. Si no fuiste tú, puedes ignorar este correo.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:20px;">
        <tr>
            <td style="border-radius:6px; background-color:#2563eb;">
                <a href="{{ $resetUrl }}" style="display:inline-block; padding:10px 20px; color:#ffffff; font-size:14px; font-weight:bold; text-decoration:none;">
                    Restablecer contraseña
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:20px 0 0; color:#6b7280; font-size:12px; line-height:1.5;">
        Este enlace expirará en {{ $expireMinutes }} minutos. Si el botón no funciona, copia y pega esta URL en tu navegador:<br>
        <span style="word-break:break-all;">{{ $resetUrl }}</span>
    </p>
@endsection
