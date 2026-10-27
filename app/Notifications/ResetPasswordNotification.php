<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(
        public string $token,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expire = (int) config(
            'auth.passwords.'.config('auth.defaults.passwords').'.expire',
            60
        );

        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $appName = (string) config('app.name', 'SoftTrace');

        return (new MailMessage)
            ->subject('Redefinição de palavra-passe — '.$appName)
            ->greeting('Olá,')
            ->line('Recebeu este email porque foi pedida a redefinição da palavra-passe da sua conta.')
            ->action('Redefinir palavra-passe', $url)
            ->line("Este link de redefinição expira em {$expire} minutos.")
            ->line('Se não pediu esta redefinição, pode ignorar este email — a sua palavra-passe mantém-se inalterada.')
            ->salutation("Cumprimentos,\n{$appName}");
    }
}
