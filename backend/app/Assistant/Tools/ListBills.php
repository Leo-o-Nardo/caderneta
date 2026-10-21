<?php

namespace App\Assistant\Tools;

use App\Enums\BillStatus;
use App\Services\BillService;
use App\Support\BillOccurrence;
use App\Support\DateRange;
use App\Support\Money;
use App\Support\Month;

/** As contas fixas de um mês, como na Agenda: pagas, atrasadas e quanto falta. */
final class ListBills extends Tool
{
    private const STATUS_LABELS = [
        'paid' => 'paga',
        'skipped' => 'pulada',
        'overdue' => 'atrasada',
        'due_today' => 'vence hoje',
        'upcoming' => 'a vencer',
    ];

    public function __construct(private readonly BillService $bills) {}

    public function name(): string
    {
        return 'list_bills';
    }

    public function description(): string
    {
        return 'As contas fixas de um mês (luz, internet, aluguel…): quais já foram pagas, quais estão atrasadas e '
            .'quanto falta pagar. Sem mês, o mês atual.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'month' => ['type' => 'string', 'description' => 'Mês no formato AAAA-MM. Sem ele, o mês atual.'],
            ],
        ];
    }

    public function status(): string
    {
        return 'Vendo as contas…';
    }

    protected function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
        ];
    }

    protected function run(array $args, ToolContext $context): ToolResult
    {
        $month = Month::parse($args['month'] ?? null, $context->timezone());
        $occurrences = $this->bills->forMonth($month, $context->today);
        // Em aberto = nem paga nem pulada.
        $open = $occurrences->filter(fn (BillOccurrence $bill) => $bill->isOpen());

        return new ToolResult([
            'month' => DateRange::monthLabel($month, $context->today),
            'bills' => $occurrences->map(fn (BillOccurrence $bill) => [
                'name' => $bill->bill->name,
                'due_on' => self::shortDate($bill->dueOn),
                'amount' => $bill->amountCents !== null ? Money::format($bill->amountCents) : null,
                // Conta de valor variável ainda não paga: o valor é uma média.
                'is_estimate' => $bill->isEstimate,
                'status' => self::STATUS_LABELS[$bill->status->value],
            ])->all(),
            'paid_count' => $occurrences->where('status', BillStatus::Paid)->count(),
            'open_count' => $open->count(),
            'open_total' => Money::format((int) $open->sum(fn (BillOccurrence $bill) => $bill->amountCents ?? 0)),
            'open_total_is_estimate' => $open->contains(fn (BillOccurrence $bill) => $bill->isEstimate),
        ]);
    }
}
