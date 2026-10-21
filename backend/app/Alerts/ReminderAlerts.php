<?php

namespace App\Alerts;

use App\Enums\Visibility;
use App\Models\Reminder;
use App\Models\User;

/**
 * Os lembretes que chegaram na hora (rotina alerts:reminders, a cada minuto). O pessoal avisa só
 * quem criou; o da casa avisa todos os membros. Cada lembrete avisa uma vez por horário: adiado,
 * ele avisa de novo no horário novo.
 */
final class ReminderAlerts
{
    public function __construct(private readonly AlertService $alerts) {}

    /** @return int quantos avisos saíram */
    public function sendDue(): int
    {
        $sent = 0;

        // Todas as casas: aqui não há contexto de casa, então nada é filtrado.
        $reminders = Reminder::withoutGlobalScopes()
            ->whereNull('done_at')
            ->whereNull('last_notified_at')
            ->where('remind_at', '<=', now())
            ->with(['household', 'creator'])
            ->lazyById();

        foreach ($reminders as $reminder) {
            $recipients = $reminder->visibility === Visibility::Private
                ? collect([$reminder->creator])->filter(fn (?User $user) => $user?->roleIn($reminder->household) !== null)
                : $reminder->household->members()->get();

            foreach ($recipients as $user) {
                $message = $this->alerts->send(
                    $user,
                    $reminder->household,
                    'reminder',
                    "reminder:{$reminder->id}:{$reminder->remind_at->utc()->format('YmdHi')}",
                    "Lembrete: {$reminder->text}",
                    self::card($reminder, $user),
                );

                $sent += $message !== null ? 1 : 0;
            }

            $reminder->forceFill(['last_notified_at' => now()])->save();
        }

        return $sent;
    }

    /** O card com "Feito", "Adiar 1 h" e "Amanhã". O horário vai no fuso de quem recebe. */
    private static function card(Reminder $reminder, User $user): array
    {
        return [
            'type' => 'reminder_due',
            'reminder' => [
                'id' => $reminder->id,
                'text' => $reminder->text,
                'remind_at' => $reminder->remind_at->setTimezone($user->timezone)->toIso8601String(),
                'recurrence' => $reminder->recurrence->value,
                'every' => $reminder->every,
            ],
            // O que a pessoa fez: null (ainda nada), "done" ou "snoozed" (com snoozed_until).
            'state' => null,
            'snoozed_until' => null,
        ];
    }
}
