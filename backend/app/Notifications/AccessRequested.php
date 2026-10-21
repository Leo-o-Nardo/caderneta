<?php

namespace App\Notifications;

use App\Mail\CadernetaMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Para o administrador: alguém se cadastrou e espera a liberação. O botão abre uma página para
 * liberar ou recusar (o link é assinado e vale por alguns dias). Vai pela fila: o cadastro não
 * espera o e-mail sair.
 */
class AccessRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly User $user) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $link = URL::temporarySignedRoute('access.review', now()->addDays(config('access.review_link_days')), ['user' => $this->user->id]);

        return CadernetaMail::make("{$this->user->name} quer entrar na Caderneta")
            ->overline('Esperando liberação', 'warn')
            ->title("{$this->user->name} quer entrar")
            ->paragraph('Criou uma conta sem convite e está esperando você liberar o acesso.')
            ->rows([
                'E-mail' => $this->user->email,
                'Cadastro' => $this->user->created_at->timezone('America/Sao_Paulo')->format('d/m/Y \à\s H:i'),
            ])
            ->button('Liberar ou recusar', $link)
            ->note('O botão abre uma página para decidir; abrir não muda nada. O link vale por '.config('access.review_link_days').' dias. Depois, dá para decidir pelo terminal: `php artisan users:pending`.')
            ->reason('Você recebe este e-mail por ser o administrador da Caderneta.')
            ->toMail();
    }
}
