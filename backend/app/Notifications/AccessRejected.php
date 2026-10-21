<?php

namespace App\Notifications;

use App\Mail\CadernetaMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Para quem se cadastrou: desta vez o acesso não foi liberado. Um aviso educado, sem detalhes. */
class AccessRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return CadernetaMail::make('Sobre o seu cadastro na Caderneta')
            ->title('Obrigado pelo interesse, '.strtok($notifiable->name, ' '))
            ->paragraph('A Caderneta ainda está numa fase de uso restrito e, por enquanto, não conseguimos liberar o seu acesso.')
            ->notice('Alguém da sua casa já usa?', 'Peça um convite: quem entra por convite tem o acesso liberado na hora.')
            ->reason('Você recebeu este e-mail porque se cadastrou na Caderneta.')
            ->toMail();
    }
}
