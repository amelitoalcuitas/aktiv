<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail(mixed $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:8080');
        $resetUrl = $frontendUrl . '/auth/reset-password?token=' . $this->token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage())
            ->subject('Reset Your Aktiv Password')
            ->view('emails.reset-password', [
                'name'     => $notifiable->name,
                'resetUrl' => $resetUrl,
            ]);
    }
}
