<?php

namespace App\Notifications;

use App\Mail\CadernetaMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Para quem se cadastrou: o acesso foi liberado e já dá para entrar. */
class AccessApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return CadernetaMail::make('Seu acesso à Caderneta foi liberado')
            ->title('Seu acesso foi liberado, '.strtok($notifiable->name, ' '))
            ->paragraph('Já dá para entrar no app com o seu e-mail e a senha que você criou.')
            ->notice('Para começar:', 'toque no microfone e fale um gasto, como "gastei 30 no mercado". O app lança, soma e mostra quanto ainda resta no mês.', 'accent')
            ->reason('Você recebeu este e-mail porque se cadastrou na Caderneta.')
            ->toMail();
    }
}
