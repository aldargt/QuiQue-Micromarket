<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; color: #1f2937; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f1f5f9; padding: 24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; background-color: #ffffff; border: 1px solid #dbe7ea; border-radius: 12px;">
                    <tr>
                        <td align="center" style="padding: 28px 32px 14px;">
                            <img src="{{ $message->embed(public_path('images/quique-logo.png')) }}" width="84" height="84" alt="QuiQue Micromarket" style="display: block; width: 84px; height: 84px; border: 0; object-fit: contain;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 40px 34px; font-size: 16px; line-height: 1.6;">
                            <h1 style="margin: 0 0 22px; color: #172b33; font-size: 24px; line-height: 1.25; text-align: center;">Restablecer contraseña</h1>
                            <p style="margin: 0 0 16px;">Hola,</p>
                            <p style="margin: 0 0 16px;">Recibimos una solicitud para restablecer la contraseña de tu cuenta.</p>
                            <p style="margin: 0 0 22px;">Para continuar, haz clic en el siguiente botón:</p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center" style="padding: 0 0 24px;">
                                        <a href="{{ $resetUrl }}" style="display: inline-block; border-radius: 8px; background-color: #2EB8CE; padding: 12px 22px; color: #ffffff; font-size: 16px; font-weight: 700; text-decoration: none;">Restablecer contraseña</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 0 0 16px;">Este enlace de restablecimiento de contraseña expirará en {{ $expirationMinutes }} minutos.</p>
                            <p style="margin: 0;">Si no solicitaste un cambio de contraseña, no es necesario realizar ninguna acción.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top: 1px solid #dbe7ea; padding: 18px 32px; color: #64748b; font-size: 13px; text-align: center;">
                            QuiQue Micromarket
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
