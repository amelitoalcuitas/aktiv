<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class AccountCreatedNotification extends ResetPassword
{
    public function toMail(mixed $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:8080');
        $setupUrl = $frontendUrl . '/auth/setup-password?token=' . $this->token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage())
            ->subject('Your Aktiv Account is Ready')
            ->view('emails.account-created', [
                'name'     => $notifiable->first_name,
                'setupUrl' => $setupUrl,
            ]);
    }
}
