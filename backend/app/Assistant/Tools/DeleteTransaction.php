<?php

namespace App\Assistant\Tools;

use App\Assistant\Actions\ActionToolBase;
use App\Assistant\Actions\FindsTransactions;
use App\Assistant\Actions\PreparedAction;
use App\Models\BillPayment;
use App\Models\Transaction;
use App\Services\TransactionService;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * "Apaga o uber de ontem". Acha o lançamento e mostra um card com o impacto antes de apagar. Se
 * a descrição bate com vários, devolve os candidatos para a IA perguntar qual.
 */
final class DeleteTransaction extends ActionToolBase
{
    use FindsTransactions;

    public function __construct(private readonly TransactionService $transactions) {}

    public function name(): string
    {
        return 'delete_transaction';
    }

    public function description(): string
    {
        return 'Apaga um lançamento ("apaga o uber de ontem"). Procura pela descrição, data e valor; se achar mais '
            .'de um, devolve os candidatos para perguntar qual (depois, chame de novo com o transaction_id). '
            .'Mostra um card com o impacto antes de apagar.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'transaction_id' => ['type' => 'integer', 'description' => 'O id, quando já se sabe qual (veio dos candidatos).'],
                'description' => ['type' => 'string', 'description' => 'Trecho da descrição, como "uber".'],
                'date' => ['type' => 'string', 'description' => 'AAAA-MM-DD, se a pessoa disse o dia.'],
                'amount' => ['type' => 'number', 'description' => 'O valor em reais, se a pessoa disse.'],
            ],
        ];
    }

    public function tier(): ToolTier
    {
        return ToolTier::Explicit;
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
        ];
    }

    public function prepare(array $args, ToolContext $context): PreparedAction|ToolResult
    {
        $transaction = $this->findTransaction($args, $context);

        if ($transaction instanceof ToolResult) {
            return $transaction;
        }

        if ($transaction->installmentPlan) {
            return ToolResult::error(sprintf(
                '"%s" é a parcela %d/%d de um parcelamento. Parcelamentos se apagam pela tela do lançamento, para não sobrar parcela solta.',
                $transaction->description,
                $transaction->installment_number,
                $transaction->installmentPlan->installments,
            ));
        }

        if (! $context->user->can('delete', $transaction)) {
            return ToolResult::error('Só quem lançou (ou o dono da casa) pode apagar esse lançamento.');
        }

        $payment = BillPayment::query()->with('bill')->where('transaction_id', $transaction->id)->first();

        $payload = [
            'transaction_id' => $transaction->id,
            // O card continua mostrando o que foi apagado depois que o lançamento some.
            'snapshot' => [
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
            ],
            // Apagar o lançamento de uma conta paga reabre aquele mês da conta.
            'reopens_bill' => $payment ? [
                'name' => $payment->bill->name,
                'month' => DateRange::monthLabel($payment->month, $context->today),
            ] : null,
        ];

        return new PreparedAction($payload, sprintf(
            'Vou apagar %s, %s, %s (%s).%s',
            $transaction->description,
            self::money($transaction->amount_cents),
            self::dayLabel($payload['snapshot']['occurred_on'], $context->today),
            $transaction->category->name,
            $payment ? " A conta {$payment->bill->name} volta a ficar em aberto." : '',
        ));
    }

    /** Apagar não tem o que editar: vale o lançamento que o card mostrou. */
    public function validatePayload(array $payload, ToolContext $context): array
    {
        Validator::make($payload, ['transaction_id' => ['required', 'integer']])->validate();

        return $payload;
    }

    public function card(array $payload, ?array $result, ToolContext $context): array
    {
        $snapshot = $payload['snapshot'];
        $month = DateRange::monthLabel(CarbonImmutable::parse($snapshot['occurred_on']), $context->today);

        return [
            'type' => 'delete_confirm',
            'transaction' => ['id' => $payload['transaction_id'], ...$snapshot],
            'impact' => sprintf(
                'Sai %s %s de %s.%s',
                self::money($snapshot['amount_cents']),
                $snapshot['kind'] === 'income' ? 'das receitas' : 'dos gastos',
                $month,
                $payload['reopens_bill'] ? " A conta {$payload['reopens_bill']['name']} de {$payload['reopens_bill']['month']} volta a ficar em aberto." : '',
            ),
        ];
    }

    public function execute(array $payload, ToolContext $context): array
    {
        $transaction = Transaction::query()->find($payload['transaction_id'])
            ?? throw ValidationException::withMessages(['action' => __('assistant.actions.gone')]);

        // O papel pode ter mudado desde o card.
        abort_unless($context->user->can('delete', $transaction), 403);

        $this->transactions->delete($transaction);

        return ['deleted_id' => $payload['transaction_id']];
    }
}
