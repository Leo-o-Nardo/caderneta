<?php

namespace App\Notifications;

use App\Mail\CadernetaMail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetCode extends Notification
{
    public function __construct(
        public readonly string $code,
        public readonly int $expiresInMinutes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // O código vai também no assunto: dá para ler na notificação do celular, sem abrir o e-mail.
        return CadernetaMail::make("Seu código para criar uma nova senha: {$this->code}")
            ->title('Seu código, '.strtok($notifiable->name, ' '))
            ->paragraph('Digite este código no app para criar uma nova senha:')
            ->code($this->code)
            ->note("Ele vale por {$this->expiresInMinutes} minutos. Se você não pediu uma nova senha, pode ignorar este e-mail: a sua senha continua a mesma.")
            ->reason('Você recebeu este e-mail porque alguém pediu uma nova senha para a sua conta.')
            ->toMail();
    }
}
