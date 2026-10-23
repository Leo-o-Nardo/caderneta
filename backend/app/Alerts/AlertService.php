<?php

namespace App\Alerts;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Events\Alerts\AlertCreated;
use App\Jobs\SendPush;
use App\Models\Alert;
use App\Models\Conversation;
use App\Models\Household;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * O único caminho de todo aviso do app (lembrete, conta vencendo, débito automático, orçamento,
 * resumo da semana): vira uma mensagem do sistema no Chat da pessoa, na casa certa, com um card,
 * e uma notificação nos celulares dela (SendPush), quando houver algum registrado.
 */
final class AlertService
{
    /**
     * Manda o aviso. $key diz o que ele é ("bill:12:2026-10:eve"): a mesma chave nunca sai duas
     * vezes para a mesma pessoa. Devolve a mensagem, ou null quando não mandou (a pessoa desligou
     * esse tipo de aviso, ou ele já tinha saído antes).
     */
    public function send(User $user, Household $household, string $kind, string $key, string $text, array $card): ?Message
    {
        if (! $user->wantsAlert($kind)) {
            return null;
        }

        $message = DB::transaction(function () use ($user, $household, $kind, $key, $text, $card) {
            // A chave única segura duas rotinas ao mesmo tempo: só uma grava, a outra não manda nada.
            $inserted = Alert::query()->insertOrIgnore([
                'user_id' => $user->id,
                'household_id' => $household->id,
                'kind' => $kind,
                'key' => $key,
            ]);

            if ($inserted === 0) {
                return null;
            }

            $message = Conversation::currentFor($user, $household)->messages()->create([
                'role' => MessageRole::Event,
                'content' => $text,
                'status' => MessageStatus::Completed,
                'metadata' => ['alert' => $kind, 'cards' => [$card]],
            ]);

            Alert::query()->where('user_id', $user->id)->where('key', $key)->update(['message_id' => $message->id]);

            return $message;
        });

        if ($message !== null) {
            // Sem o Reverb, o app vê o aviso na próxima vez que buscar: falhar aqui não desfaz nada.
            try {
                event(new AlertCreated($user->id, $message));
            } catch (Throwable $error) {
                report($error);
            }

            // Só põe na fila quando há para onde mandar: quem nunca aceitou notificações não gera trabalho.
            if ($user->pushTokens()->exists()) {
                SendPush::dispatch($message->id)->afterCommit();
            }
        }

        return $message;
    }

    /** Se o aviso $key já saiu para a pessoa: poupa montar de novo um aviso caro (o resumo da semana). */
    public function wasSent(User $user, string $key): bool
    {
        return Alert::query()->where('user_id', $user->id)->where('key', $key)->exists();
    }

    /** O resultado de um botão do aviso ("Feito", "Paga"): o card guardado na mensagem muda. */
    public function updateCard(Message $message, array $card): void
    {
        $metadata = $message->metadata ?? [];
        $metadata['cards'] = [$card];

        $message->forceFill(['metadata' => $metadata])->save();
    }

    /**
     * Quem pode lançar na casa (dono e membros): quem recebe os avisos de conta. Quem é só leitura
     * não paga conta pelo app.
     *
     * @return Collection<int, User>
     */
    public static function writers(Household $household): Collection
    {
        return $household->members()->get()->filter(fn (User $user) => $user->membership->role->canWrite())->values();
    }
}
