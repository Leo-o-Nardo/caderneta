<?php

namespace App\Assistant\Tools;

use App\Assistant\Actions\ActionToolBase;
use App\Assistant\Actions\FindsTransactions;
use App\Assistant\Actions\PreparedAction;
use App\Assistant\Actions\RequestRules;
use App\Assistant\NameMatcher;
use App\Enums\CategoryKind;
use App\Http\Requests\Finance\UpdateTransactionRequest;
use App\Models\Card;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "O café de ontem foi 15, não 12", "muda o uber para Lazer", "o almoço foi no Nubank". Acha o
 * lançamento como o "apaga" e mostra um card com o antes e o depois; nada muda antes de confirmar.
 */
final class UpdateTransaction extends ActionToolBase
{
    use FindsTransactions;

    private const PAYMENT_NAMES = ['pix' => 'Pix', 'debit' => 'Débito', 'cash' => 'Dinheiro', 'credit_card' => 'Crédito'];

    public function __construct(private readonly TransactionService $transactions) {}

    public function name(): string
    {
        return 'update_transaction';
    }

    public function description(): string
    {
        return 'Corrige um lançamento já feito ("o café de ontem foi 15, não 12", "muda o uber para Lazer", "o almoço foi '
            .'no Nubank"). description, date e amount acham o lançamento como ele está; os campos new_* dizem o que muda. '
            .'Se achar mais de um, devolve os candidatos para perguntar qual (depois, chame de novo com o transaction_id). '
            .'Mostra um card com o antes e o depois para a pessoa confirmar.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'transaction_id' => ['type' => 'integer', 'description' => 'O id, quando já se sabe qual (veio dos candidatos).'],
                'description' => ['type' => 'string', 'description' => 'Trecho da descrição atual, como "uber".'],
                'date' => ['type' => 'string', 'description' => 'AAAA-MM-DD do lançamento, se a pessoa disse o dia.'],
                'amount' => ['type' => 'number', 'description' => 'O valor atual em reais, se a pessoa disse.'],
                'new_amount' => ['type' => 'number', 'description' => 'O valor certo, em reais.'],
                'new_description' => ['type' => 'string'],
                'new_category' => ['type' => 'string', 'description' => 'Nome da categoria certa, como na lista.'],
                'new_date' => ['type' => 'string', 'description' => 'AAAA-MM-DD certo.'],
                'new_payment' => ['type' => 'string', 'enum' => ['debit', 'credit_card', 'pix', 'cash']],
                'new_card' => ['type' => 'string', 'description' => 'Nome do cartão, se passou a ser no crédito.'],
            ],
        ];
    }

    public function tier(): ToolTier
    {
        return ToolTier::Confirm;
    }

    public function status(): string
    {
        return 'Procurando o lançamento…';
    }

    protected function rules(): array
    {
        return [
            'transaction_id' => ['nullable', 'integer'],
            'description' => ['required_without:transaction_id', 'nullable', 'string', 'max:60'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'new_amount' => ['nullable', 'numeric', 'min:0.01', 'max:1000000000'],
            'new_description' => ['nullable', 'string', 'max:120'],
            'new_category' => ['nullable', 'string', 'max:60'],
            'new_date' => ['nullable', 'date_format:Y-m-d'],
            'new_payment' => ['nullable', Rule::in(['debit', 'credit_card', 'pix', 'cash'])],
            'new_card' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function prepare(array $args, ToolContext $context): PreparedAction|ToolResult
    {
        $asked = Arr::only($args, ['new_amount', 'new_description', 'new_category', 'new_date', 'new_payment', 'new_card']);

        if (array_filter($asked, fn (mixed $value) => $value !== null) === []) {
            return ToolResult::error('O que mudar nesse lançamento? Dá para corrigir o valor, a descrição, a categoria, a data ou a forma de pagamento.');
        }

        $transaction = $this->findTransaction($args, $context);

        if ($transaction instanceof ToolResult) {
            return $transaction;
        }

        if ($transaction->installmentPlan) {
            return ToolResult::error(sprintf(
                '"%s" é a parcela %d/%d de um parcelamento. Parcelamentos se corrigem pela tela do lançamento, para todas as parcelas mudarem juntas.',
                $transaction->description,
                $transaction->installment_number,
                $transaction->installmentPlan->installments,
            ));
        }

        if (! $context->user->can('update', $transaction)) {
            return ToolResult::error('Só quem lançou (ou o dono da casa) pode corrigir esse lançamento.');
        }

        $changes = $this->changes($args, $transaction, $context);

        if ($changes instanceof ToolResult) {
            return $changes;
        }

        if ($changes === []) {
            return ToolResult::error(sprintf('%s já está assim.', $transaction->description));
        }

        $rows = $this->rows(self::snapshot($transaction), $changes);

        return new PreparedAction(
            ['transaction_id' => $transaction->id, 'changes' => $changes],
            sprintf(
                'Vou corrigir %s (%s, %s): %s.',
                $transaction->description,
                self::money($transaction->amount_cents),
                self::dayLabel($transaction->occurred_on->toDateString(), $context->today),
                self::join(array_map(fn (array $row) => mb_strtolower($row['label'])." de {$row['before']} para {$row['after']}", $rows)),
            ),
        );
    }

    /**
     * As regras da tela de editar lançamento (UpdateTransactionRequest). O "antes" do card é tirado
     * do banco, nunca do que chegou: o lançamento ainda não mudou.
     */
    public function validatePayload(array $payload, ToolContext $context): array
    {
        $transaction = Transaction::query()->with(['category', 'card'])->find($payload['transaction_id'] ?? null)
            ?? throw ValidationException::withMessages(['action' => __('assistant.actions.gone')]);

        return [
            'transaction_id' => $transaction->id,
            'changes' => RequestRules::validate(UpdateTransactionRequest::class, (array) ($payload['changes'] ?? []), route: ['transaction' => $transaction]),
            'before' => self::snapshot($transaction),
        ];
    }

    public function card(array $payload, ?array $result, ToolContext $context): array
    {
        $before = $payload['before'];

        return [
            'type' => 'transaction_update',
            'transaction' => ['id' => $payload['transaction_id'], ...Arr::only($before, ['kind', 'description', 'amount_cents', 'occurred_on', 'category'])],
            'changes' => $this->rows($before, $payload['changes']),
        ];
    }

    public function execute(array $payload, ToolContext $context): array
    {
        $transaction = Transaction::query()->find($payload['transaction_id'])
            ?? throw ValidationException::withMessages(['action' => __('assistant.actions.gone')]);

        // O papel pode ter mudado desde o card.
        abort_unless($context->user->can('update', $transaction), 403);

        $this->transactions->update($transaction, $payload['changes']);

        return ['transaction_id' => $transaction->id];
    }

    /**
     * Os campos que mudam, já no formato da API. Fica só o que é diferente do que está gravado; a
     * forma de pagamento e o cartão andam juntos.
     */
    private function changes(array $args, Transaction $transaction, ToolContext $context): array|ToolResult
    {
        $changes = [];

        if (isset($args['new_amount'])) {
            $changes['amount_cents'] = self::cents($args['new_amount']);
        }

        if (isset($args['new_description'])) {
            $changes['description'] = self::capitalize($args['new_description']);
        }

        if (isset($args['new_date'])) {
            $changes['occurred_on'] = $args['new_date'];
        }

        if (isset($args['new_category'])) {
            $categories = Category::query()->active()->where('kind', $transaction->kind)->orderBy('position')->orderBy('id')->get();
            $category = NameMatcher::find($categories, $args['new_category'], fn (Category $item) => $item->name);

            if (! $category) {
                return ToolResult::error(sprintf(
                    'Não encontrei a categoria "%s". As de %s são: %s.',
                    $args['new_category'],
                    $transaction->kind === CategoryKind::Income ? 'receita' : 'despesa',
                    $categories->pluck('name')->join(', '),
                ));
            }

            $changes['category_id'] = $category->id;
        }

        if (isset($args['new_payment']) || isset($args['new_card'])) {
            if ($transaction->kind === CategoryKind::Income) {
                return ToolResult::error('Receita não tem forma de pagamento.');
            }

            $payment = isset($args['new_card']) ? 'credit_card' : $args['new_payment'];
            $card = null;

            if ($payment === 'credit_card') {
                $card = $this->resolveCard(Card::query()->active()->orderBy('name')->get(), $args['new_card'] ?? null, $context);

                if ($card instanceof ToolResult) {
                    return $card;
                }
            }

            $changes['payment_type'] = $payment;
            $changes['card_id'] = $card?->id;
        }

        $current = [
            'amount_cents' => $transaction->amount_cents,
            'description' => $transaction->description,
            'occurred_on' => $transaction->occurred_on->toDateString(),
            'category_id' => $transaction->category_id,
            'payment_type' => $transaction->payment_type?->value,
            'card_id' => $transaction->card_id,
        ];

        $samePayment = ($changes['payment_type'] ?? null) === $current['payment_type'] && ($changes['card_id'] ?? null) === $current['card_id'];

        return array_filter(
            $changes,
            fn (mixed $value, string $field) => in_array($field, ['payment_type', 'card_id'], true) ? ! $samePayment : $value !== $current[$field],
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /**
     * Uma linha por campo que muda, já escrita para a tela: "Valor: R$ 12,00 → R$ 15,00".
     *
     * @return list<array{field: string, label: string, before: string, after: string}>
     */
    private function rows(array $before, array $changes): array
    {
        $rows = [];

        if (array_key_exists('amount_cents', $changes)) {
            $rows[] = ['field' => 'amount', 'label' => 'Valor', 'before' => self::money($before['amount_cents']), 'after' => self::money($changes['amount_cents'])];
        }

        if (array_key_exists('description', $changes)) {
            $rows[] = ['field' => 'description', 'label' => 'Descrição', 'before' => $before['description'], 'after' => $changes['description']];
        }

        if (array_key_exists('category_id', $changes)) {
            $rows[] = [
                'field' => 'category',
                'label' => 'Categoria',
                'before' => $before['category']['name'],
                'after' => Category::query()->find($changes['category_id'])?->name ?? '—',
            ];
        }

        if (array_key_exists('occurred_on', $changes)) {
            $rows[] = ['field' => 'date', 'label' => 'Data', 'before' => self::shortDate($before['occurred_on']), 'after' => self::shortDate($changes['occurred_on'])];
        }

        if (array_key_exists('payment_type', $changes)) {
            $card = isset($changes['card_id']) ? Card::query()->find($changes['card_id']) : null;

            $rows[] = [
                'field' => 'payment',
                'label' => 'Pagamento',
                'before' => self::paymentName($before['payment_type'], $before['card']['name'] ?? null),
                'after' => self::paymentName($changes['payment_type'], $card?->name),
            ];
        }

        return $rows;
    }

    /** O lançamento como está agora, para o "antes" do card. */
    private static function snapshot(Transaction $transaction): array
    {
        return [
            'kind' => $transaction->kind->value,
            'description' => $transaction->description,
            'amount_cents' => $transaction->amount_cents,
            'occurred_on' => $transaction->occurred_on->toDateString(),
            'category' => [
                'id' => $transaction->category->id,
                'name' => $transaction->category->name,
                'icon' => $transaction->category->icon,
                'color' => $transaction->category->color,
            ],
            'payment_type' => $transaction->payment_type?->value,
            'card' => $transaction->card ? ['id' => $transaction->card->id, 'name' => $transaction->card->name] : null,
        ];
    }

    /** "Pix", "Crédito (Nubank)". */
    private static function paymentName(?string $type, ?string $card): string
    {
        $name = self::PAYMENT_NAMES[$type] ?? '—';

        return $type === 'credit_card' && $card ? "{$name} ({$card})" : $name;
    }
}
