<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class ChangePasswordNotification extends ResetPasswordNotification
{
    public function toMail(mixed $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:8080');
        $resetUrl = $frontendUrl . '/auth/reset-password?token=' . $this->token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage())
            ->subject('Change Your Aktiv Password')
            ->view('emails.change-password', [
                'name'     => $notifiable->name,
                'resetUrl' => $resetUrl,
            ]);
    }
}
