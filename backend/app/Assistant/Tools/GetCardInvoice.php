<?php

namespace App\Assistant\Tools;

use App\Assistant\NameMatcher;
use App\Models\Card;
use App\Models\Transaction;
use App\Services\InvoiceService;
use App\Support\DateRange;
use App\Support\InvoiceCalendar;
use App\Support\Money;
use App\Support\Month;

/** A fatura de um cartão: o mesmo cálculo da tela da fatura (InvoiceService). */
final class GetCardInvoice extends Tool
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function name(): string
    {
        return 'get_card_invoice';
    }

    public function description(): string
    {
        return 'A fatura de um cartão de crédito: total, fechamento, vencimento, quanto é de parcelas e as maiores '
            .'compras. Sem mês, a fatura em aberto hoje (a que recebe uma compra feita agora).';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'card' => ['type' => 'string', 'description' => 'Nome do cartão. Sem ele, o cartão padrão de quem pergunta.'],
                'month' => ['type' => 'string', 'description' => 'Mês da fatura (o mês em que ela fecha), no formato AAAA-MM.'],
            ],
        ];
    }

    public function status(): string
    {
        return 'Abrindo a fatura…';
    }

    protected function rules(): array
    {
        return [
            'card' => ['nullable', 'string', 'max:60'],
            'month' => ['nullable', 'date_format:Y-m'],
        ];
    }

    protected function run(array $args, ToolContext $context): ToolResult
    {
        $cards = Card::query()->active()->with('user')->orderBy('name')->get();

        if ($cards->isEmpty()) {
            return ToolResult::error('A casa ainda não tem cartões cadastrados. Dá para cadastrar na Agenda, em Cartões.');
        }

        if (isset($args['card'])) {
            $card = NameMatcher::find($cards, $args['card'], fn (Card $item) => $item->name);

            if (! $card) {
                return ToolResult::error(sprintf('Não encontrei o cartão "%s". Os cartões são: %s.', $args['card'], $cards->pluck('name')->join(', ')));
            }
        } else {
            // Sem nome: o padrão de quem pergunta; se a casa só tem um cartão, ele.
            $card = $cards->first(fn (Card $item) => $item->is_default && $item->user_id === $context->user->id)
                ?? ($cards->count() === 1 ? $cards->first() : null);

            if (! $card) {
                return ToolResult::error('Qual cartão? Os cartões da casa são: '.$cards->pluck('name')->join(', ').'.');
            }
        }

        $period = isset($args['month'])
            ? InvoiceCalendar::forMonth($card->closing_day, $card->due_day, Month::parse($args['month'], $context->timezone()))
            : $card->invoiceFor($context->today);

        $items = $this->invoices->items($card, $period);
        $total = (int) $items->sum('amount_cents');

        return new ToolResult([
            'card' => $card->name,
            'owner' => $card->user->name,
            'month' => DateRange::monthLabel($period->month, $context->today),
            'closes_on' => self::shortDate($period->closesOn),
            'due_on' => self::shortDate($period->dueOn),
            // No dia do fechamento a fatura já fechou, como na tela da fatura.
            'is_closed' => $context->today->startOfDay()->greaterThanOrEqualTo($period->closesOn),
            'total' => Money::format($total),
            'installments' => Money::format((int) $items->whereNotNull('installment_plan_id')->sum('amount_cents')),
            'count' => $items->count(),
            'biggest' => $items->sortByDesc('amount_cents')->take(5)->values()->map(fn (Transaction $item) => array_filter([
                'description' => $item->description,
                'amount' => Money::format($item->amount_cents),
                'date' => self::shortDate($item->occurred_on),
                'installment' => $item->installmentPlan
                    ? "{$item->installment_number}/{$item->installmentPlan->installments}"
                    : null,
            ]))->all(),
        ]);
    }
}
