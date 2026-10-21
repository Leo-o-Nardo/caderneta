<?php

namespace App\Services;

use App\Models\HouseholdInvitation;
use App\Models\User;
use App\Notifications\AccessApproved;
use App\Notifications\AccessRejected;
use App\Notifications\AccessRequested;
use App\Notifications\MemberJoinedByInvite;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Quem pode entrar no app. Quem se cadastra sozinho fica esperando: o administrador (ADMIN_EMAIL)
 * recebe um e-mail para liberar ou recusar. Quem vem por convite já entra liberado, e o
 * administrador só é avisado.
 */
class AccessService
{
    /** Cadastro sem convite: fica esperando, e o administrador recebe o pedido. */
    public function request(User $user): void
    {
        $this->notifyAdmin(new AccessRequested($user));
    }

    /** Cadastro por convite: entra liberado; o administrador recebe um aviso. */
    public function joinedByInvite(User $user, HouseholdInvitation $invitation): void
    {
        $user->forceFill(['approved_at' => now(), 'rejected_at' => null])->save();

        $this->notifyAdmin(new MemberJoinedByInvite($user, $invitation));
    }

    /** Libera o acesso (também de quem tinha sido recusado) e avisa a pessoa. */
    public function approve(User $user): void
    {
        if ($user->approved_at !== null) {
            return;
        }

        $user->forceFill(['approved_at' => now(), 'rejected_at' => null])->save();
        $user->notify(new AccessApproved);
    }

    /**
     * Recusa: o cadastro fica guardado como recusado, a pessoa recebe um aviso educado e, se já
     * tinha entrado em algum aparelho, sai de todos.
     */
    public function reject(User $user): void
    {
        if ($user->rejected_at !== null) {
            return;
        }

        $user->forceFill(['approved_at' => null, 'rejected_at' => now()])->save();
        $user->tokens()->delete();
        $user->notify(new AccessRejected);
    }

    private function notifyAdmin(object $notification): void
    {
        $admin = config('access.admin_email');

        if (blank($admin)) {
            // Sem administrador configurado, o pedido continua valendo: dá para liberar pelo terminal.
            Log::warning('ADMIN_EMAIL não configurado: o aviso de cadastro não foi enviado.', ['notification' => $notification::class]);

            return;
        }

        Notification::route('mail', $admin)->notify($notification);
    }
}
