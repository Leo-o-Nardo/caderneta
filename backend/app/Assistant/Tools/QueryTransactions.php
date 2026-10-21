<?php

namespace App\Assistant\Tools;

use App\Assistant\NameMatcher;
use App\Enums\CategoryKind;
use App\Models\Card;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\DateRange;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Soma os lançamentos de um período, com filtros e agrupamento. É a ferramenta das perguntas de
 * "quanto": quanto gastei com mercado, quanto recebi em setembro, gastos por dia da semana passada.
 */
final class QueryTransactions extends Tool
{
    private const PAYMENT_LABELS = [
        'debit' => 'Débito',
        'credit_card' => 'Cartão de crédito',
        'pix' => 'Pix',
        'cash' => 'Dinheiro',
    ];

    private const GROUP_NAMES = [
        'category' => 'categoria',
        'day' => 'dia',
        'month' => 'mês',
        'payment' => 'forma de pagamento',
    ];

    public function name(): string
    {
        return 'query_transactions';
    }

    public function description(): string
    {
        return 'Soma os lançamentos de um período, com filtros opcionais por categoria, cartão e trecho da descrição, '
            .'e pode agrupar por categoria, dia, mês ou forma de pagamento. Use para perguntas como '
            .'"quanto gastei com mercado esse mês?", "quanto foi de uber semana passada?" ou "quanto recebi em setembro?".';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'from' => ['type' => 'string', 'description' => 'Data inicial, no formato AAAA-MM-DD.'],
                'to' => ['type' => 'string', 'description' => 'Data final (incluída), no formato AAAA-MM-DD.'],
                'kind' => ['type' => 'string', 'enum' => ['expense', 'income'], 'description' => 'Gastos (padrão) ou receitas.'],
                'category' => ['type' => 'string', 'description' => 'Nome da categoria, como na lista de categorias.'],
                'card' => ['type' => 'string', 'description' => 'Nome do cartão de crédito, para ver só as compras nele.'],
                'text' => ['type' => 'string', 'description' => 'Trecho da descrição, como "uber" ou "ifood".'],
                'group_by' => [
                    'type' => 'string',
                    'enum' => ['category', 'day', 'month', 'payment'],
                    'description' => 'Agrupa o total. Use "category" quando a pergunta não citar categoria.',
                ],
            ],
            'required' => ['from', 'to'],
        ];
    }

    public function status(): string
    {
        return 'Consultando lançamentos…';
    }

    protected function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'kind' => ['nullable', Rule::in(['expense', 'income'])],
            'category' => ['nullable', 'string', 'max:60'],
            'card' => ['nullable', 'string', 'max:60'],
            'text' => ['nullable', 'string', 'max:60'],
            'group_by' => ['nullable', Rule::in(array_keys(self::GROUP_NAMES))],
        ];
    }

    protected function run(array $args, ToolContext $context): ToolResult
    {
        $range = DateRange::parse($args['from'], $args['to'], $context->timezone());

        if ($range->days() > 366) {
            return ToolResult::error('Escolha um período de até um ano.');
        }

        $kind = CategoryKind::from($args['kind'] ?? 'expense');
        $category = null;
        $card = null;

        if (isset($args['category'])) {
            $categories = Category::query()->active()->where('kind', $kind)->orderBy('position')->get();
            $category = NameMatcher::find($categories, $args['category'], fn (Category $item) => $item->name);

            if (! $category) {
                return ToolResult::error(sprintf(
                    'Não encontrei a categoria "%s". As de %s são: %s.',
                    $args['category'],
                    $kind === CategoryKind::Expense ? 'despesa' : 'receita',
                    $categories->pluck('name')->join(', '),
                ));
            }
        }

        if (isset($args['card'])) {
            $cards = Card::query()->active()->orderBy('name')->get();
            $card = NameMatcher::find($cards, $args['card'], fn (Card $item) => $item->name);

            if (! $card) {
                return ToolResult::error(sprintf(
                    'Não encontrei o cartão "%s". Os cartões são: %s.',
                    $args['card'],
                    $cards->pluck('name')->join(', ') ?: 'nenhum',
                ));
            }
        }

        $query = Transaction::query()
            ->where('transactions.kind', $kind)
            ->whereBetween('transactions.occurred_on', [$range->from->toDateString(), $range->to->toDateString()])
            ->when($category, fn (Builder $query, Category $category) => $query->where('transactions.category_id', $category->id))
            ->when($card, fn (Builder $query, Card $card) => $query->where('transactions.card_id', $card->id))
            ->when($args['text'] ?? null, fn (Builder $query, string $text) => $query->whereLike('transactions.description', "%{$text}%"));

        $totals = (clone $query)
            ->selectRaw('count(*) as count, coalesce(sum(transactions.amount_cents), 0) as total')
            ->toBase()
            ->first();

        $total = (int) $totals->total;
        $label = $range->label($context->today);
        $groupBy = $args['group_by'] ?? null;
        $groups = $groupBy ? $this->groups(clone $query, $groupBy) : [];

        $data = array_filter([
            'period' => ['from' => $range->from->toDateString(), 'to' => $range->to->toDateString(), 'label' => $label],
            'kind' => $kind->value,
            'category' => $category?->name,
            'card' => $card?->name,
            'text' => $args['text'] ?? null,
            'count' => (int) $totals->count,
            'total_cents' => $total,
            'total' => Money::format($total),
            // Para a IA, sem as cores (são só do gráfico).
            'groups' => $groups ? array_map(fn (array $group) => array_diff_key($group, ['color' => true]), array_slice($groups, 0, 12)) : null,
        ], fn ($value) => $value !== null);

        $cards = $groups ? [$this->chart($groups, $groupBy, $kind, $label, $total)] : [];

        return new ToolResult($data, $cards);
    }

    private function groups(Builder $query, string $groupBy): array
    {
        $query = match ($groupBy) {
            'category' => $query
                ->join('categories', 'categories.id', '=', 'transactions.category_id')
                ->groupBy('categories.id', 'categories.name', 'categories.color')
                ->selectRaw('categories.name as label, categories.color as color')
                ->orderByDesc('total'),
            'day' => $query
                ->groupBy('transactions.occurred_on')
                ->selectRaw('transactions.occurred_on as label')
                ->orderBy('label'),
            'month' => $query
                ->groupByRaw("to_char(transactions.occurred_on, 'YYYY-MM')")
                ->selectRaw("to_char(transactions.occurred_on, 'YYYY-MM') as label")
                ->orderBy('label'),
            'payment' => $query
                ->groupBy('transactions.payment_type')
                ->selectRaw('transactions.payment_type as label')
                ->orderByDesc('total'),
        };

        return $query
            ->selectRaw('count(*) as count, sum(transactions.amount_cents) as total')
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                'label' => $this->groupLabel($groupBy, $row->label),
                'color' => $row->color ?? null,
                'count' => (int) $row->count,
                'total_cents' => (int) $row->total,
                'total' => Money::format((int) $row->total),
            ])
            ->all();
    }

    private function groupLabel(string $groupBy, ?string $value): string
    {
        return match ($groupBy) {
            'day' => self::shortDate((string) $value),
            // "2026-09" → "set/26".
            'month' => mb_substr(DateRange::MONTHS[(int) substr((string) $value, 5, 2)], 0, 3).'/'.substr((string) $value, 2, 2),
            'payment' => self::PAYMENT_LABELS[$value] ?? 'Sem forma de pagamento',
            default => (string) $value,
        };
    }

    /** O card de gráfico de barras. Por categoria, as 5 maiores e o resto somado em "Outras". */
    private function chart(array $groups, string $groupBy, CategoryKind $kind, string $label, int $total): array
    {
        if ($groupBy === 'category' && count($groups) > 6) {
            $rest = array_slice($groups, 5);
            $restTotal = array_sum(array_column($rest, 'total_cents'));
            $groups = [
                ...array_slice($groups, 0, 5),
                ['label' => 'Outras', 'color' => null, 'total_cents' => $restTotal, 'total' => Money::format($restTotal)],
            ];
        }

        return [
            'type' => 'bar_chart',
            'title' => ($kind === CategoryKind::Expense ? 'Gastos' : 'Receitas').' por '.self::GROUP_NAMES[$groupBy],
            'subtitle' => mb_strtoupper(mb_substr($label, 0, 1)).mb_substr($label, 1),
            'total' => Money::format($total),
            'bars' => array_map(fn (array $group) => [
                'label' => $group['label'],
                'value_cents' => $group['total_cents'],
                'value' => $group['total'],
                'color' => $group['color'],
            ], $groups),
        ];
    }
}
