<?php

namespace App\Notifications;

use App\Mail\CadernetaMail;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Para o administrador: alguém tocou em "Reportar resposta" no Chat. Vai a resposta do assistente e
 * a pergunta que veio antes, para revisar. Vai pela fila: o app não espera o e-mail sair.
 */
class ResponseReported extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Message $answer,
        public readonly ?Message $question,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $conversation = $this->answer->conversation;

        return CadernetaMail::make('Uma resposta do assistente foi reportada')
            ->overline('Resposta reportada', 'warn')
            ->title('Uma resposta foi reportada')
            ->paragraph("{$conversation->user->name} marcou uma resposta do assistente como ofensiva, errada ou estranha.")
            ->rows([
                'E-mail' => $conversation->user->email,
                'Casa' => $conversation->household->name,
                'Resposta de' => $this->answer->created_at->timezone('America/Sao_Paulo')->format('d/m/Y \à\s H:i'),
            ])
            ->paragraph('Pergunta: '.($this->question?->content ?? '(nenhuma antes da resposta)'))
            ->notice('Resposta:', (string) $this->answer->content)
            ->reason('Você recebe este e-mail por ser o administrador da Caderneta.')
            ->toMail();
    }
}
