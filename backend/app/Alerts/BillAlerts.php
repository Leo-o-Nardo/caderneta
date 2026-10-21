<?php

namespace App\Alerts;

use App\Enums\TransactionSource;
use App\Models\Household;
use App\Models\RecurringBill;
use App\Models\User;
use App\Services\BillService;
use App\Support\BillOccurrence;
use App\Support\HouseholdContext;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Os avisos das contas fixas (rotinas de hora em hora):
 * - alerts:bills: a conta vence amanhã (véspera) ou vence hoje e segue em aberto, a partir das 9h
 *   no fuso de cada pessoa. Só para quem pode lançar (dono e membros).
 * - bills:autopay: no dia do vencimento, a conta de débito automático é lançada sozinha (no débito)
 *   e todos que podem lançar são avisados, com "Desfazer". Se o valor muda todo mês, não dá para
 *   lançar: o aviso pede o valor.
 */
final class BillAlerts
{
    // A partir de que hora (no fuso de cada pessoa) saem os avisos de conta.
    public const HOUR = 9;

    // Se a rotina ficou parada, o débito automático ainda lança até 3 dias depois do vencimento.
    private const AUTOPAY_GRACE_DAYS = 3;

    public function __construct(
        private readonly AlertService $alerts,
        private readonly BillService $bills,
        private readonly HouseholdContext $context,
    ) {}

    /** @return int quantos avisos saíram */
    public function sendDue(): int
    {
        $sent = 0;

        // Todas as casas com contas: sem o filtro automático de casa (o contexto é preenchido
        // depois, pessoa por pessoa).
        $households = Household::query()
            ->whereHas('recurringBills', fn ($query) => $query->withoutGlobalScopes()->whereNull('archived_at')->where('autopay', false))
            ->get();

        foreach ($households as $household) {
            foreach (AlertService::writers($household) as $user) {
                $now = CarbonImmutable::now($user->timezone);

                if ($now->hour < self::HOUR) {
                    continue;
                }

                // As contas e os pagamentos da casa, como esta pessoa vê.
                $this->context->set($household, $user->membership->role, $user);

                // As de débito automático têm o aviso próprio (bills:autopay).
                $bills = RecurringBill::query()->active()->where('autopay', false)->with(['category', 'payments.transaction'])->get();

                foreach ($bills as $bill) {
                    foreach (['eve' => $now->addDay(), 'day' => $now] as $when => $date) {
                        $occurrence = $this->bills->occurrence($bill, $date->startOfMonth(), $now);

                        if (! $occurrence?->isOpen() || $occurrence->dueOn->toDateString() !== $date->toDateString()) {
                            continue;
                        }

                        $message = $this->alerts->send(
                            $user,
                            $household,
                            'bill_due',
                            "bill:{$bill->id}:{$occurrence->month->format('Y-m')}:{$when}",
                            sprintf('%s vence %s%s.', $bill->name, $when === 'eve' ? 'amanhã' : 'hoje', self::amount($occurrence)),
                            self::card($occurrence, $when === 'eve' ? 'tomorrow' : 'today'),
                        );

                        $sent += $message !== null ? 1 : 0;
                    }
                }
            }
        }

        return $sent;
    }

    /** @return int quantas contas foram tratadas (lançadas ou avisadas) */
    public function runAutopay(): int
    {
        $handled = 0;

        $bills = RecurringBill::withoutGlobalScopes()
            ->whereNull('archived_at')
            ->where('autopay', true)
            ->with(['household', 'creator'])
            ->lazyById();

        foreach ($bills as $bill) {
            try {
                $handled += $this->autopay($bill) ? 1 : 0;
            } catch (Throwable $error) {
                // Uma conta com problema não pode impedir as outras.
                report($error);
            }
        }

        return $handled;
    }

    private function autopay(RecurringBill $bill): bool
    {
        // O lançamento sai em nome de quem cadastrou a conta, no fuso dela.
        $author = $bill->creator;
        $role = $author?->roleIn($bill->household);

        if (! $role?->canWrite()) {
            return false;
        }

        $today = CarbonImmutable::now($author->timezone)->startOfDay();
        $month = $today->startOfMonth();

        // Este mês já foi tratado (lançado, desfeito de propósito ou avisado): não repete.
        if ($bill->autopay_through !== null && $bill->autopay_through->format('Y-m') >= $month->format('Y-m')) {
            return false;
        }

        $this->context->set($bill->household, $role, $author);
        $bill->load(['category', 'payments.transaction']);
        $occurrence = $this->bills->occurrence($bill, $month, $today);

        // Só no dia do vencimento (ou poucos dias depois, se a rotina ficou parada).
        if ($occurrence === null || $occurrence->dueOn->gt($today) || $occurrence->dueOn->lt($today->subDays(self::AUTOPAY_GRACE_DAYS))) {
            return false;
        }

        // Paga à mão antes do dia: nada a lançar.
        if ($occurrence->isOpen()) {
            $this->launchOrAsk($bill, $occurrence, $author, $today);
        }

        $bill->forceFill(['autopay_through' => $month->toDateString()])->save();

        return true;
    }

    private function launchOrAsk(RecurringBill $bill, BillOccurrence $occurrence, User $author, CarbonImmutable $today): void
    {
        $key = "autopay:{$bill->id}:{$occurrence->month->format('Y-m')}";

        // Valor que muda todo mês: a estimativa nunca vira despesa sozinha. O aviso pede o valor.
        if ($bill->amount_varies || $bill->expected_cents === null) {
            foreach (AlertService::writers($bill->household) as $user) {
                $this->alerts->send(
                    $user,
                    $bill->household,
                    'autopay',
                    $key,
                    "{$bill->name} é no débito automático e vence hoje: informe o valor para lançar.",
                    self::card($occurrence, 'autopay'),
                );
            }

            return;
        }

        $paid = $this->bills->pay($bill, $author, [
            'month' => $occurrence->month->format('Y-m'),
            'payment_type' => 'debit',
            'paid_on' => $occurrence->dueOn->toDateString(),
        ], $today, TransactionSource::Recurring);

        foreach (AlertService::writers($bill->household) as $user) {
            $this->alerts->send(
                $user,
                $bill->household,
                'autopay',
                $key,
                sprintf('%s: %s lançada no débito automático.', $bill->name, Money::format((int) $paid->amountCents)),
                [
                    'type' => 'autopay_launched',
                    'bill' => self::billCard($bill),
                    'month' => $paid->month->format('Y-m'),
                    'amount_cents' => $paid->amountCents,
                    'transaction_id' => $paid->payment?->transaction_id,
                    // null, ou "undone" depois do "Desfazer".
                    'state' => null,
                ],
            );
        }
    }

    /** " (R$ 99,90)", " (~R$ 170,00)" para estimativa, ou nada quando não há valor. */
    private static function amount(BillOccurrence $occurrence): string
    {
        if ($occurrence->amountCents === null) {
            return '';
        }

        return ' ('.($occurrence->isEstimate ? '~' : '').Money::format($occurrence->amountCents).')';
    }

    /**
     * O card de conta: "Paguei" (abre a tela de pagar) e "Lembrar amanhã". $when: "tomorrow",
     * "today" ou "autopay" (débito automático de valor variável, esperando o valor).
     */
    private static function card(BillOccurrence $occurrence, string $when): array
    {
        return [
            'type' => 'bill_due',
            'bill' => self::billCard($occurrence->bill),
            'month' => $occurrence->month->format('Y-m'),
            'due_on' => $occurrence->dueOn->toDateString(),
            'amount_cents' => $occurrence->amountCents,
            'is_estimate' => $occurrence->isEstimate,
            'when' => $when,
            // null, "paid" ou "reminded" (com reminder_at).
            'state' => null,
            'reminder_at' => null,
        ];
    }

    private static function billCard(RecurringBill $bill): array
    {
        return [
            'id' => $bill->id,
            'name' => $bill->name,
            'amount_varies' => $bill->amount_varies,
            'category' => [
                'id' => $bill->category->id,
                'name' => $bill->category->name,
                'icon' => $bill->category->icon,
                'color' => $bill->category->color,
            ],
        ];
    }
}
