<?php

namespace App\Assistant\Tools;

use App\Assistant\Actions\ActionToolBase;
use App\Assistant\Actions\PreparedAction;
use App\Assistant\Actions\RequestRules;
use App\Assistant\NameMatcher;
use App\Enums\TransactionSource;
use App\Http\Requests\Finance\PayBillRequest;
use App\Models\Card;
use App\Models\RecurringBill;
use App\Services\BillService;
use App\Support\DateRange;
use App\Support\Month;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "Paguei a luz": marca o mês em aberto como pago e lança a despesa, como o "Paguei" da Agenda.
 * Conta de valor variável sem o valor dito vem com o campo vazio no card: a estimativa nunca vira
 * despesa sozinha.
 */
final class MarkBillPaid extends ActionToolBase
{
    public function __construct(private readonly BillService $bills) {}

    public function name(): string
    {
        return 'mark_bill_paid';
    }

    public function description(): string
    {
        return 'Marca uma conta fixa como paga ("paguei a luz", "paguei 182,40 de luz") e lança a despesa junto. '
            .'Vira um card para confirmar. Em conta de valor variável sem valor dito, o card pede o valor.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'bill' => ['type' => 'string', 'description' => 'Nome da conta, como na lista de contas fixas.'],
                'amount' => ['type' => 'number', 'description' => 'Quanto foi pago, em reais. Só se a pessoa disse.'],
                'date' => ['type' => 'string', 'description' => 'AAAA-MM-DD do pagamento. Sem data dita, hoje.'],
                'payment' => ['type' => 'string', 'enum' => ['debit', 'credit_card', 'pix', 'cash']],
                'card' => ['type' => 'string', 'description' => 'Nome do cartão, se foi no crédito.'],
            ],
            'required' => ['bill'],
        ];
    }

    public function tier(): ToolTier
    {
        return ToolTier::Confirm;
    }

    public function status(): string
    {
        return 'Preparando o pagamento…';
    }

    protected function rules(): array
    {
        return [
            'bill' => ['required', 'string', 'max:60'],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:1000000000'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'payment' => ['nullable', Rule::in(['debit', 'credit_card', 'pix', 'cash'])],
            'card' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function prepare(array $args, ToolContext $context): PreparedAction|ToolResult
    {
        $bills = RecurringBill::query()->active()->with(['category', 'payments.transaction'])->orderBy('name')->get();

        if ($bills->isEmpty()) {
            return ToolResult::error('A casa ainda não tem contas fixas. Dá para cadastrar na Agenda.');
        }

        $bill = NameMatcher::find($bills, $args['bill'], fn (RecurringBill $item) => $item->name);

        if (! $bill) {
            return ToolResult::error(sprintf('Não encontrei a conta "%s". As contas fixas são: %s.', $args['bill'], $bills->pluck('name')->join(', ')));
        }

        $open = $this->bills->openOccurrence($bill, $context->today);

        if (! $open) {
            return ToolResult::error("A conta {$bill->name} já está em dia.");
        }

        // Conta paga sem forma dita: Pix (a ordem para contas é Pix, débito, dinheiro e cartão).
        $payment = isset($args['card']) ? 'credit_card' : ($args['payment'] ?? 'pix');
        $card = null;

        if ($payment === 'credit_card') {
            $card = $this->resolveCard(Card::query()->active()->orderBy('name')->get(), $args['card'] ?? null, $context);

            if ($card instanceof ToolResult) {
                return $card;
            }
        }

        // Sem valor dito: o fixo da conta. Valor variável (ou sem valor cadastrado) fica em branco no card.
        $amount = isset($args['amount']) ? self::cents($args['amount']) : ($bill->amount_varies ? null : $bill->expected_cents);

        $payload = [
            'bill_id' => $bill->id,
            'month' => $open->month->format('Y-m'),
            'amount_cents' => $amount,
            'paid_on' => $args['date'] ?? $context->today->toDateString(),
            'payment_type' => $payment,
            'card_id' => $card?->id,
        ];

        $month = DateRange::monthLabel($open->month, $context->today);

        if ($amount === null) {
            $estimate = $this->bills->estimate($bill);

            return new PreparedAction($payload, sprintf(
                'Vou marcar %s de %s como paga. O valor muda todo mês%s: falta dizer quanto deu, e dá para digitar no card.',
                $bill->name,
                $month,
                $estimate ? ' (a média é ~'.self::money($estimate).')' : '',
            ));
        }

        return new PreparedAction($payload, sprintf(
            'Vou marcar %s de %s como paga: %s, %s, %s.',
            $bill->name,
            $month,
            self::money($amount),
            self::paymentLabel($payment, $card?->name),
            self::dayLabel($payload['paid_on'], $context->today),
        ));
    }

    /** As regras do "Paguei" da Agenda (PayBillRequest), mais a conta, que precisa ser da casa e estar ativa. */
    public function validatePayload(array $payload, ToolContext $context): array
    {
        $billRule = function (string $attribute, mixed $value, Closure $fail) {
            if (! RecurringBill::query()->active()->whereKey($value)->exists()) {
                $fail(__('assistant.actions.gone'));
            }
        };

        Validator::make($payload, ['bill_id' => ['bail', 'required', 'integer', $billRule]])->validate();

        return [
            'bill_id' => (int) $payload['bill_id'],
            ...RequestRules::validate(PayBillRequest::class, Arr::except($payload, 'bill_id')),
        ];
    }

    public function card(array $payload, ?array $result, ToolContext $context): array
    {
        $bill = RecurringBill::query()->with(['category', 'payments.transaction'])->find($payload['bill_id']);
        $month = Month::parse($payload['month'], $context->timezone());
        $occurrence = $bill ? $this->bills->occurrence($bill, $month, $context->today) : null;
        $card = isset($payload['card_id']) ? Card::query()->find($payload['card_id']) : null;

        return [
            'type' => 'bill_confirm',
            'bill' => $bill ? [
                'id' => $bill->id,
                'name' => $bill->name,
                'amount_varies' => $bill->amount_varies,
                'category' => ['id' => $bill->category->id, 'name' => $bill->category->name, 'icon' => $bill->category->icon, 'color' => $bill->category->color],
            ] : null,
            'month' => $payload['month'],
            'due_on' => $occurrence?->dueOn->toDateString(),
            // Em branco quando a conta varia e ninguém disse o valor: o app pede antes de confirmar.
            'amount_cents' => $payload['amount_cents'] ?? null,
            'estimate_cents' => $bill ? $this->bills->estimate($bill) : null,
            'paid_on' => $payload['paid_on'] ?? null,
            'payment_type' => $payload['payment_type'] ?? null,
            'card' => $card ? ['id' => $card->id, 'name' => $card->name] : null,
        ];
    }

    public function execute(array $payload, ToolContext $context): array
    {
        $bill = RecurringBill::query()->active()->find($payload['bill_id'])
            ?? throw ValidationException::withMessages(['action' => __('assistant.actions.gone')]);

        // O BillService recusa conta variável sem valor ("amount_cents"): o app mostra no campo.
        $occurrence = $this->bills->pay($bill, $context->user, Arr::except($payload, 'bill_id'), $context->today, TransactionSource::Assistant);

        return ['transaction_id' => $occurrence->payment?->transaction_id, 'month' => $payload['month']];
    }
}
