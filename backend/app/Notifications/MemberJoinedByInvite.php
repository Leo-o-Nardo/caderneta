<?php

namespace App\Notifications;

use App\Mail\CadernetaMail;
use App\Models\HouseholdInvitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Para o administrador, só para saber: alguém se cadastrou por convite e já entrou (quem convidou
 * responde pela pessoa).
 */
class MemberJoinedByInvite extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly User $user,
        public readonly HouseholdInvitation $invitation,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $inviter = $this->invitation->inviter?->name ?? 'alguém da casa';

        return CadernetaMail::make("{$this->user->name} entrou na Caderneta pelo convite de {$inviter}")
            ->overline('Já liberado', 'accent')
            ->title("{$this->user->name} entrou pelo convite de {$inviter}")
            ->paragraph('Como veio por convite, o acesso já foi liberado. Não precisa fazer nada.')
            ->rows([
                'E-mail' => $this->user->email,
                'Casa' => $this->invitation->household->name,
                'Papel' => $this->invitation->role->label(),
            ])
            ->reason('Você recebe este e-mail por ser o administrador da Caderneta.')
            ->toMail();
    }
}
