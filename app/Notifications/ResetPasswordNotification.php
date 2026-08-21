<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        $expirationMinutes = (int) config(
            'auth.passwords.'.config('auth.defaults.passwords').'.expire'
        );

        return (new MailMessage)
            ->subject('Restablecer contraseña')
            ->view([
                'html' => 'emails.auth.reset-password',
                'text' => 'emails.auth.reset-password-text',
            ], [
                'resetUrl' => $url,
                'expirationMinutes' => $expirationMinutes,
            ]);
    }
}
