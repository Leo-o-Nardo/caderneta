<?php

namespace App\Alerts;

use App\Models\Message;
use App\Models\RecurringBill;
use App\Models\Reminder;
use App\Models\User;
use App\Services\BillService;
use App\Services\ReminderService;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Os botões dos cards de aviso. Cada um faz a ação e guarda o resultado no card ("Feito",
 * "Adiado para 15:00", "Paga"), para o aviso continuar mostrando o que aconteceu.
 * O HouseholdContext já está na casa do aviso (ver AlertController).
 */
final class AlertActions
{
    public function __construct(
        private readonly AlertService $alerts,
        private readonly ReminderService $reminders,
        private readonly BillService $bills,
    ) {}

    /** @throws ValidationException quando o botão não vale para este aviso (ou a ação não deu) */
    public function run(Message $message, string $action, User $user): void
    {
        $card = $message->cards()[0] ?? [];
        $now = CarbonImmutable::now($user->timezone);

        $card = match ([$card['type'] ?? null, $action]) {
            ['reminder_due', 'done'] => $this->reminderDone($card, $user),
            ['reminder_due', 'snooze_hour'] => $this->reminderSnooze($card, $user, $now->addHour()->format('Y-m-d H:i')),
            // "Amanhã": às 9h do dia seguinte (só a data vira 9h no ReminderService).
            ['reminder_due', 'tomorrow'] => $this->reminderSnooze($card, $user, $now->addDay()->toDateString()),
            ['bill_due', 'remind_tomorrow'] => $this->remindTomorrow($card, $user, $now),
            ['bill_due', 'paid'] => $this->billPaid($card, $now),
            ['autopay_launched', 'undo'] => $this->undoAutopay($card, $user),
            default => throw ValidationException::withMessages(['action' => __('alerts.invalid_action')]),
        };

        $this->alerts->updateCard($message, $card);
    }

    /** "Feito". Se alguém da casa já resolveu este lembrete, o card só passa a mostrar isso. */
    private function reminderDone(array $card, User $user): array
    {
        $reminder = $this->sameReminder($card);

        if ($reminder !== null) {
            $this->reminders->done($reminder, $user);
        }

        return [...$card, 'state' => 'done'];
    }

    private function reminderSnooze(array $card, User $user, string $until): array
    {
        $reminder = $this->sameReminder($card)
            ?? throw ValidationException::withMessages(['action' => __('alerts.reminder_gone')]);

        $this->reminders->snooze($reminder, $user, $until);

        return [
            ...$card,
            'state' => 'snoozed',
            'snoozed_until' => $reminder->refresh()->remind_at->setTimezone($user->timezone)->toIso8601String(),
        ];
    }

    /**
     * O lembrete do card, se ele ainda está no mesmo aviso (não foi concluído nem adiado por outra
     * pessoa da casa, nem apagado). Null quando já foi resolvido.
     */
    private function sameReminder(array $card): ?Reminder
    {
        $reminder = Reminder::query()->find($card['reminder']['id'] ?? null);

        if ($reminder === null || $reminder->done_at !== null) {
            return null;
        }

        $shown = CarbonImmutable::parse($card['reminder']['remind_at']);

        return $reminder->remind_at->equalTo($shown) ? $reminder : null;
    }

    /** "Lembrar amanhã": um lembrete pessoal, amanhã às 9h, que avisa de novo. */
    private function remindTomorrow(array $card, User $user, CarbonImmutable $now): array
    {
        $reminder = $this->reminders->create($user, [
            'text' => "Pagar {$card['bill']['name']}",
            'remind_at' => $now->addDay()->toDateString(),
            'recurrence' => 'none',
            'visibility' => 'private',
        ]);

        return [...$card, 'state' => 'reminded', 'reminder_at' => $reminder->remind_at->setTimezone($user->timezone)->toIso8601String()];
    }

    /** Chamado pelo app depois de pagar pela tela: confere que o mês ficou pago mesmo. */
    private function billPaid(array $card, CarbonImmutable $now): array
    {
        $bill = RecurringBill::query()->with(['category', 'payments.transaction'])->find($card['bill']['id'] ?? null);
        $occurrence = $bill ? $this->bills->occurrence($bill, Month::parse($card['month'], $now->timezoneName), $now) : null;

        if ($occurrence === null || $occurrence->isOpen()) {
            throw ValidationException::withMessages(['action' => __('alerts.bill_not_paid')]);
        }

        return [...$card, 'state' => 'paid'];
    }

    /** "Desfazer" o débito automático: apaga a despesa e o mês volta a ficar em aberto. Não é lançado de novo. */
    private function undoAutopay(array $card, User $user): array
    {
        $bill = RecurringBill::query()->find($card['bill']['id'] ?? null);

        if ($bill !== null) {
            try {
                $this->bills->undo($bill, Month::parse($card['month'], $user->timezone));
            } catch (ModelNotFoundException) {
                // O pagamento já tinha sido desfeito (pela Agenda, ou por outra pessoa da casa).
            }
        }

        return [...$card, 'state' => 'undone'];
    }
}
