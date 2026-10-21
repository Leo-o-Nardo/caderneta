<?php

namespace App\Assistant\Tools;

use App\Assistant\Actions\ActionToolBase;
use App\Assistant\Actions\PreparedAction;
use App\Assistant\Actions\RequestRules;
use App\Assistant\NameMatcher;
use App\Enums\CategoryKind;
use App\Enums\TransactionSource;
use App\Http\Requests\Finance\StoreInstallmentPlanRequest;
use App\Http\Requests\Finance\StoreTransactionRequest;
use App\Models\Card;
use App\Models\Category;
use App\Services\InstallmentService;
use App\Services\TransactionService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * "Gastei 12 de café e 30 de uber", "comprei uma TV de 2.500 em 10x no Nubank". Monta os
 * lançamentos e mostra no card para a pessoa conferir; nada é gravado antes de ela confirmar.
 */
final class CreateTransactions extends ActionToolBase
{
    private const ITEM_FIELDS = ['kind', 'amount_cents', 'description', 'occurred_on', 'category_id', 'payment_type', 'card_id'];

    public function __construct(
        private readonly TransactionService $transactions,
        private readonly InstallmentService $installments,
    ) {}

    public function name(): string
    {
        return 'create_transactions';
    }

    public function description(): string
    {
        return 'Lança um ou mais gastos ou receitas ditos pela pessoa ("gastei 12 de café e 30 de uber", '
            .'"recebi o salário de 8.500"). Compras parceladas no cartão usam installments. Nada é gravado: '
            .'vira um card para a pessoa conferir e confirmar.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'kind' => ['type' => 'string', 'enum' => ['expense', 'income'], 'description' => 'Gasto (padrão) ou receita.'],
                            'amount' => ['type' => 'number', 'description' => 'Valor em reais, como 12.5. Na compra parcelada, o total.'],
                            'description' => ['type' => 'string', 'description' => 'Curta, como a pessoa disse: "Café", "Uber".'],
                            'category' => ['type' => 'string', 'description' => 'Nome da categoria, como na lista. Sem certeza, deixe vazio.'],
                            'date' => ['type' => 'string', 'description' => 'AAAA-MM-DD. Sem data dita, hoje.'],
                            'payment' => ['type' => 'string', 'enum' => ['debit', 'credit_card', 'pix', 'cash']],
                            'card' => ['type' => 'string', 'description' => 'Nome do cartão, se foi no crédito.'],
                            'installments' => ['type' => 'integer', 'description' => 'Número de parcelas (2 a 48), só no cartão.'],
                        ],
                        'required' => ['amount', 'description'],
                    ],
                ],
            ],
            'required' => ['items'],
        ];
    }

    public function tier(): ToolTier
    {
        return ToolTier::Confirm;
    }

    public function status(): string
    {
        return 'Montando o lançamento…';
    }

    protected function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.kind' => ['nullable', Rule::in(['expense', 'income'])],
            'items.*.amount' => ['required', 'numeric', 'min:0.01', 'max:1000000000'],
            'items.*.description' => ['required', 'string', 'max:120'],
            'items.*.category' => ['nullable', 'string', 'max:60'],
            'items.*.date' => ['nullable', 'date_format:Y-m-d'],
            'items.*.payment' => ['nullable', Rule::in(['debit', 'credit_card', 'pix', 'cash'])],
            'items.*.card' => ['nullable', 'string', 'max:60'],
            'items.*.installments' => ['nullable', 'integer', 'between:1,48'],
        ];
    }

    public function prepare(array $args, ToolContext $context): PreparedAction|ToolResult
    {
        $categories = Category::query()->active()->orderBy('position')->orderBy('id')->get();
        $cards = Card::query()->active()->orderBy('name')->get();
        $items = [];

        foreach ($args['items'] as $item) {
            $kind = CategoryKind::from($item['kind'] ?? 'expense');
            $ofKind = $categories->where('kind', $kind)->values();

            // Categoria que não existe (ou não foi dita) vira "Outros": a pessoa troca no card se quiser.
            $category = (isset($item['category']) ? NameMatcher::find($ofKind, $item['category'], fn (Category $c) => $c->name) : null)
                ?? $ofKind->first(fn (Category $c) => NameMatcher::normalize($c->name) === 'outros')
                ?? $ofKind->last();

            $installments = (int) ($item['installments'] ?? 1);
            $payment = null;
            $card = null;

            if ($kind === CategoryKind::Expense) {
                $saidCredit = $installments > 1 || isset($item['card']) || ($item['payment'] ?? null) === 'credit_card';

                if ($saidCredit) {
                    // Parcelado, com cartão citado ou "no crédito": precisa achar o cartão (ou perguntar qual).
                    $payment = 'credit_card';
                    $card = $this->resolveCard($cards, $item['card'] ?? null, $context);

                    if ($card instanceof ToolResult) {
                        return $card;
                    }
                } elseif (isset($item['payment'])) {
                    $payment = $item['payment'];
                } else {
                    // Nada dito: compra é no crédito, no cartão de quem fala. Quem não tem cartão, Pix.
                    $card = $this->ownCard($cards, $context);
                    $payment = $card ? 'credit_card' : 'pix';
                }
            }

            $items[] = array_filter([
                'kind' => $kind->value,
                'amount_cents' => self::cents($item['amount']),
                'description' => self::capitalize($item['description']),
                'occurred_on' => $item['date'] ?? $context->today->toDateString(),
                'category_id' => $category?->id,
                'payment_type' => $payment,
                'card_id' => $card?->id,
                'installments' => $installments > 1 && $kind === CategoryKind::Expense ? $installments : null,
            ], fn ($value) => $value !== null);
        }

        return new PreparedAction(['items' => $items], $this->summary($items, $categories, $cards, $context));
    }

    /**
     * Cada item passa pelas regras do formulário do app: o de lançamento, ou o de parcelamento quando
     * tem parcelas. Os erros saem como "items.1.category_id", para o app marcar o item certo.
     */
    public function validatePayload(array $payload, ToolContext $context): array
    {
        Validator::make($payload, ['items' => ['required', 'array', 'min:1', 'max:20']])->validate();
        $items = [];

        foreach (array_values($payload['items']) as $index => $item) {
            $item = is_array($item) ? $item : [];
            $installments = (int) ($item['installments'] ?? 1);

            if ($installments > 1) {
                $plan = RequestRules::validate(StoreInstallmentPlanRequest::class, [
                    'description' => $item['description'] ?? null,
                    'category_id' => $item['category_id'] ?? null,
                    'card_id' => $item['card_id'] ?? null,
                    'installments' => $installments,
                    'total_cents' => $item['amount_cents'] ?? null,
                    'purchased_on' => $item['occurred_on'] ?? null,
                ], "items.{$index}.");

                $items[] = [
                    'kind' => CategoryKind::Expense->value,
                    'amount_cents' => $plan['total_cents'],
                    'description' => $plan['description'],
                    'occurred_on' => $plan['purchased_on'],
                    'category_id' => $plan['category_id'],
                    'payment_type' => 'credit_card',
                    'card_id' => $plan['card_id'],
                    'installments' => $plan['installments'],
                ];

                continue;
            }

            $valid = RequestRules::validate(StoreTransactionRequest::class, Arr::only($item, self::ITEM_FIELDS), "items.{$index}.");
            $items[] = Arr::only($valid, self::ITEM_FIELDS);
        }

        return ['items' => $items];
    }

    public function card(array $payload, ?array $result, ToolContext $context): array
    {
        $categories = Category::query()->whereKey(array_column($payload['items'], 'category_id'))->get()->keyBy('id');
        $cards = Card::query()->whereKey(array_filter(array_column($payload['items'], 'card_id')))->get()->keyBy('id');

        return [
            'type' => 'transactions_confirm',
            'items' => array_map(function (array $item) use ($categories, $cards) {
                $category = $categories->get($item['category_id']);
                $card = isset($item['card_id']) ? $cards->get($item['card_id']) : null;

                return [
                    'kind' => $item['kind'],
                    'amount_cents' => $item['amount_cents'],
                    'description' => $item['description'],
                    'occurred_on' => $item['occurred_on'],
                    'category' => $category ? ['id' => $category->id, 'name' => $category->name, 'icon' => $category->icon, 'color' => $category->color] : null,
                    'payment_type' => $item['payment_type'] ?? null,
                    'card' => $card ? ['id' => $card->id, 'name' => $card->name] : null,
                    'installments' => $item['installments'] ?? null,
                ];
            }, $payload['items']),
            'total_cents' => array_sum(array_column($payload['items'], 'amount_cents')),
        ];
    }

    /** Tudo ou nada: se um item falhar, nenhum fica gravado. */
    public function execute(array $payload, ToolContext $context): array
    {
        return DB::transaction(function () use ($payload, $context) {
            $transactionIds = [];
            $planIds = [];

            foreach ($payload['items'] as $item) {
                if (($item['installments'] ?? 1) > 1) {
                    $planIds[] = $this->installments->create($context->user, [
                        'description' => $item['description'],
                        'category_id' => $item['category_id'],
                        'card_id' => $item['card_id'],
                        'installments' => $item['installments'],
                        'total_cents' => $item['amount_cents'],
                        'purchased_on' => $item['occurred_on'],
                    ], TransactionSource::Assistant)->id;

                    continue;
                }

                $transactionIds[] = $this->transactions->create(
                    $context->user,
                    Arr::only($item, self::ITEM_FIELDS),
                    TransactionSource::Assistant,
                )->id;
            }

            return ['transaction_ids' => $transactionIds, 'installment_plan_ids' => $planIds];
        });
    }

    /** "Vou lançar: Café R$ 12,00 (Alimentação) e Uber R$ 30,00 (Transporte), no débito, hoje." */
    private function summary(array $items, Collection $categories, Collection $cards, ToolContext $context): string
    {
        $names = array_map(function (array $item) use ($categories) {
            $installments = isset($item['installments']) ? " em {$item['installments']}x" : '';
            $category = $categories->firstWhere('id', $item['category_id'])?->name;

            return "{$item['description']} ".self::money($item['amount_cents']).$installments.($category ? " ({$category})" : '');
        }, $items);

        // Forma de pagamento e data só no fim quando são as mesmas para todos (senão, o card mostra).
        $payments = array_unique(array_map(fn (array $item) => self::paymentLabel(
            $item['payment_type'] ?? null,
            isset($item['card_id']) ? $cards->firstWhere('id', $item['card_id'])?->name : null,
        ), $items));
        $dates = array_unique(array_column($items, 'occurred_on'));

        $details = array_filter([
            count($payments) === 1 ? reset($payments) : null,
            count($dates) === 1 ? self::dayLabel(reset($dates), $context->today) : null,
        ]);

        return 'Vou lançar: '.self::join($names).($details ? ', '.implode(', ', $details) : '').'.';
    }
}
