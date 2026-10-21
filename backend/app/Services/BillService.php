<?php

namespace App\Services;

use App\Enums\BillStatus;
use App\Enums\CategoryKind;
use App\Enums\PaymentType;
use App\Enums\TransactionSource;
use App\Models\BillPayment;
use App\Models\RecurringBill;
use App\Models\User;
use App\Support\BillOccurrence;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Contas fixas. Os meses de cada conta não são gravados: são calculados a partir do dia de
 * vencimento. Só fica no banco o mês que foi pago ou pulado (BillPayment). Assim não existe
 * rotina para gerar meses, nem meses faltando se ela falhar.
 *
 * $today é sempre "agora" no fuso do usuário.
 */
class BillService
{
    /** Quantos pagamentos recentes entram na média da estimativa de uma conta de valor variável. */
    private const ESTIMATE_PAYMENTS = 3;

    public function __construct(private readonly TransactionService $transactions) {}

    public function create(User $author, array $data, CarbonImmutable $today): RecurringBill
    {
        $bill = new RecurringBill(Arr::except($data, 'starts_on'));
        $bill->created_by = $author->id;
        $bill->starts_on = isset($data['starts_on'])
            ? Month::parse($data['starts_on'], $today->timezoneName)
            : $this->firstMonth($bill->due_day, $today);
        $bill->save();

        return $bill;
    }

    /** O mês $month da conta, ou null se a conta ainda não existia nele. */
    public function occurrence(RecurringBill $bill, CarbonImmutable $month, CarbonImmutable $today): ?BillOccurrence
    {
        $month = $month->startOfMonth();

        if ($month->format('Y-m') < $bill->starts_on->format('Y-m')) {
            return null;
        }

        $dueOn = Month::day($month, $bill->due_day);
        $payment = $bill->payments->first(
            fn (BillPayment $payment) => $payment->month->format('Y-m') === $month->format('Y-m')
        );
        $days = (int) round($today->startOfDay()->diffInDays($dueOn, false));

        $status = match (true) {
            $payment !== null => $payment->status,
            $days < 0 => BillStatus::Overdue,
            $days === 0 => BillStatus::DueToday,
            default => BillStatus::Upcoming,
        };

        $paidAmount = $payment?->transaction?->amount_cents;

        return new BillOccurrence(
            bill: $bill,
            month: $month,
            dueOn: $dueOn,
            amountCents: $paidAmount ?? $this->estimate($bill),
            isEstimate: $paidAmount === null && $bill->amount_varies,
            status: $status,
            daysUntilDue: $days,
            payment: $payment,
        );
    }

    /**
     * O valor que entra nos totais a pagar. Valor fixo: o esperado. Valor variável: a média dos
     * últimos pagamentos, que acompanha o consumo real; sem pagamentos ainda, a estimativa do cadastro.
     */
    public function estimate(RecurringBill $bill): ?int
    {
        if (! $bill->amount_varies) {
            return $bill->expected_cents;
        }

        $recent = $bill->payments
            ->filter(fn (BillPayment $payment) => $payment->status === BillStatus::Paid && $payment->transaction)
            ->sortByDesc(fn (BillPayment $payment) => $payment->month->getTimestamp())
            ->take(self::ESTIMATE_PAYMENTS)
            ->map(fn (BillPayment $payment) => $payment->transaction->amount_cents);

        return $recent->isEmpty() ? $bill->expected_cents : (int) round($recent->avg());
    }

    /**
     * Os últimos $months meses da conta (pagos, pulados, atrasados), do mais recente ao mais
     * antigo, para a tela de detalhe. Meses antes de a conta existir ficam de fora.
     *
     * @return Collection<int, BillOccurrence>
     */
    public function history(RecurringBill $bill, CarbonImmutable $today, int $months = 6): Collection
    {
        $current = $today->startOfMonth();

        return collect(range(0, $months - 1))
            ->map(fn (int $back) => $this->occurrence($bill, $current->subMonths($back), $today))
            ->filter()
            ->values();
    }

    /**
     * Todas as contas ativas num mês, em ordem de vencimento.
     *
     * @return Collection<int, BillOccurrence>
     */
    public function forMonth(CarbonImmutable $month, CarbonImmutable $today): Collection
    {
        return $this->activeBills()
            ->map(fn (RecurringBill $bill) => $this->occurrence($bill, $month, $today))
            ->filter()
            ->sortBy(fn (BillOccurrence $occurrence) => $occurrence->dueOn->getTimestamp())
            ->values();
    }

    /**
     * As contas em aberto que já venceram (no mês passado ou neste) e as que vencem nos próximos
     * $days dias. Atrasos mais antigos que o mês passado ficam de fora, para a lista não crescer para sempre.
     *
     * @return Collection<int, BillOccurrence>
     */
    public function upcoming(CarbonImmutable $today, int $days = 30): Collection
    {
        $until = $today->startOfDay()->addDays($days);

        $months = [];
        for ($month = $today->startOfMonth()->subMonth(); $month->lte($until); $month = $month->addMonth()) {
            $months[] = $month;
        }

        return $this->activeBills()
            ->flatMap(fn (RecurringBill $bill) => array_map(
                fn (CarbonImmutable $month) => $this->occurrence($bill, $month, $today),
                $months,
            ))
            ->filter(fn (?BillOccurrence $occurrence) => $occurrence?->isOpen() && $occurrence->dueOn->lte($until))
            ->sortBy(fn (BillOccurrence $occurrence) => $occurrence->dueOn->getTimestamp())
            ->values();
    }

    /** O mês que um "paguei" agora quitaria: o mais antigo em aberto entre o passado, o atual e o próximo. */
    public function openOccurrence(RecurringBill $bill, CarbonImmutable $today): ?BillOccurrence
    {
        $current = $today->startOfMonth();

        foreach ([$current->subMonth(), $current, $current->addMonth()] as $month) {
            $occurrence = $this->occurrence($bill, $month, $today);

            if ($occurrence?->isOpen()) {
                return $occurrence;
            }
        }

        return null;
    }

    /**
     * "Paguei a luz": marca o mês como pago e lança a despesa, as duas coisas juntas.
     * Sem valor informado, usa o esperado; sem forma de pagamento, considera Pix. Conta de valor
     * variável sempre pede o valor exato: a estimativa nunca vira despesa sozinha.
     */
    public function pay(
        RecurringBill $bill,
        User $author,
        array $data,
        CarbonImmutable $today,
        TransactionSource $source = TransactionSource::App,
    ): BillOccurrence {
        return DB::transaction(function () use ($bill, $author, $data, $today, $source) {
            $month = $this->settleableMonth($bill, $data['month'] ?? null, $today);
            $amount = $data['amount_cents'] ?? ($bill->amount_varies ? null : $bill->expected_cents);

            if ($amount === null) {
                throw ValidationException::withMessages(['amount_cents' => __('finance.bill_amount_required')]);
            }

            $transaction = $this->transactions->create($author, [
                'kind' => CategoryKind::Expense->value,
                'amount_cents' => $amount,
                'description' => $bill->name,
                'occurred_on' => $data['paid_on'] ?? $today->toDateString(),
                'category_id' => $bill->category_id,
                'payment_type' => $data['payment_type'] ?? PaymentType::Pix->value,
                'card_id' => $data['card_id'] ?? null,
            ], $source);

            $bill->payments()->create([
                'month' => $month->toDateString(),
                'status' => BillStatus::Paid,
                'transaction_id' => $transaction->id,
            ]);

            return $this->occurrence($bill->load('payments.transaction'), $month, $today);
        });
    }

    /** "Esse mês não veio": o mês sai da lista de pendentes, sem lançar despesa. */
    public function skip(RecurringBill $bill, ?string $month, CarbonImmutable $today): BillOccurrence
    {
        return DB::transaction(function () use ($bill, $month, $today) {
            $month = $this->settleableMonth($bill, $month, $today);

            $bill->payments()->create([
                'month' => $month->toDateString(),
                'status' => BillStatus::Skipped,
            ]);

            return $this->occurrence($bill->load('payments.transaction'), $month, $today);
        });
    }

    /** Desfaz o pagamento ou o pulo de um mês. Se foi pago, a despesa é apagada junto. */
    public function undo(RecurringBill $bill, CarbonImmutable $month): void
    {
        $payment = $bill->payments()->where('month', $month->toDateString())->firstOrFail();

        DB::transaction(function () use ($payment) {
            // Apagar a despesa já apaga o pagamento (cascade); sem despesa, apaga o pagamento direto.
            if ($payment->transaction) {
                $payment->transaction->delete();
            } else {
                $payment->delete();
            }
        });
    }

    /** O mês atual, se o vencimento dele ainda não passou; senão, o próximo. */
    private function firstMonth(int $dueDay, CarbonImmutable $today): CarbonImmutable
    {
        $month = $today->startOfMonth();

        return Month::day($month, $dueDay)->lt($today->startOfDay()) ? $month->addMonth() : $month;
    }

    /**
     * O mês a pagar ou pular: o informado ("2026-10") ou, sem mês, o mais antigo em aberto.
     * Trava a conta, para que dois "paguei" ao mesmo tempo não gravem o mesmo mês.
     */
    private function settleableMonth(RecurringBill $bill, ?string $requested, CarbonImmutable $today): CarbonImmutable
    {
        RecurringBill::query()->whereKey($bill->id)->lockForUpdate()->first();
        $bill->load('payments.transaction');

        if (! $requested) {
            return $this->openOccurrence($bill, $today)?->month
                ?? throw ValidationException::withMessages(['month' => __('finance.bill_already_settled')]);
        }

        $month = Month::parse($requested, $today->timezoneName);
        $occurrence = $this->occurrence($bill, $month, $today);

        if (! $occurrence) {
            throw ValidationException::withMessages(['month' => __('finance.bill_month_before_start')]);
        }

        if (! $occurrence->isOpen()) {
            throw ValidationException::withMessages(['month' => __('finance.bill_already_settled')]);
        }

        return $month;
    }

    /** @return Collection<int, RecurringBill> */
    private function activeBills(): Collection
    {
        return RecurringBill::query()
            ->active()
            ->with(['category', 'payments.transaction'])
            ->orderBy('due_day')
            ->orderBy('name')
            ->get()
            ->toBase();
    }
}
