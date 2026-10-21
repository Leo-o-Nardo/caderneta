<?php

namespace App\Assistant\Tools;

use App\Assistant\Actions\ActionToolBase;
use App\Assistant\Actions\PreparedAction;
use App\Assistant\Actions\RequestRules;
use App\Assistant\NameMatcher;
use App\Enums\CategoryKind;
use App\Http\Requests\Finance\StoreBillRequest;
use App\Models\Category;
use App\Models\RecurringBill;
use App\Services\BillService;
use App\Support\Month;

/**
 * "Cadastra a internet de 99,90 todo dia 10", "a luz vence dia 15 e o valor muda". Grava na hora,
 * com "Desfazer", como o "Cadastrar conta" da Agenda. Sem valor dito, a conta fica com valor
 * variável: o valor é dito a cada pagamento.
 */
final class CreateRecurringBill extends ActionToolBase
{
    public function __construct(private readonly BillService $bills) {}

    public function name(): string
    {
        return 'create_recurring_bill';
    }

    public function description(): string
    {
        return 'Cadastra uma conta fixa, que vence todo mês ("cadastra a internet de 99,90 todo dia 10", "a luz vence '
            .'dia 15 e o valor muda"). Sem valor dito, fica com valor variável (o valor é dito a cada pagamento). '
            .'Grava na hora e o card tem "Desfazer".';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Curto: "Internet", "Luz", "Aluguel".'],
                'amount' => ['type' => 'number', 'description' => 'O valor em reais. Na de valor variável, a estimativa (opcional).'],
                'amount_varies' => ['type' => 'boolean', 'description' => 'O valor muda todo mês (luz, água).'],
                'due_day' => ['type' => 'integer', 'description' => 'O dia do vencimento (1 a 31).'],
                'category' => ['type' => 'string', 'description' => 'Nome da categoria de despesa. Sem certeza, deixe vazio.'],
                'autopay' => ['type' => 'boolean', 'description' => 'Débito automático.'],
            ],
            'required' => ['name', 'due_day'],
        ];
    }

    public function tier(): ToolTier
    {
        return ToolTier::Immediate;
    }

    public function status(): string
    {
        return 'Cadastrando a conta…';
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'amount' => ['nullable', 'numeric', 'min:0.01', 'max:1000000000'],
            'amount_varies' => ['nullable', 'boolean'],
            'due_day' => ['required', 'integer', 'between:1,31'],
            'category' => ['nullable', 'string', 'max:60'],
            'autopay' => ['nullable', 'boolean'],
        ];
    }

    public function prepare(array $args, ToolContext $context): PreparedAction|ToolResult
    {
        $existing = NameMatcher::find(RecurringBill::query()->active()->get(), $args['name'], fn (RecurringBill $bill) => $bill->name);

        if ($existing && NameMatcher::normalize($existing->name) === NameMatcher::normalize($args['name'])) {
            return ToolResult::error("Já existe a conta fixa {$existing->name}. Para mudar o valor ou o dia, é pela Agenda.");
        }

        $categories = Category::query()->active()->where('kind', CategoryKind::Expense)->orderBy('position')->orderBy('id')->get();
        $category = self::pickCategory($categories, $args['category'] ?? null, ['Contas da casa', 'Outros']);
        $amount = isset($args['amount']) ? self::cents($args['amount']) : null;
        // Sem valor dito, só dá para saber quanto foi na hora de pagar: é uma conta de valor variável.
        $varies = $amount === null || (bool) ($args['amount_varies'] ?? false);

        $payload = [
            'name' => self::capitalize($args['name']),
            'category_id' => $category?->id,
            'expected_cents' => $amount,
            'amount_varies' => $varies,
            'due_day' => (int) $args['due_day'],
            'autopay' => (bool) ($args['autopay'] ?? false),
        ];

        return new PreparedAction($payload, sprintf(
            'Pronto: conta fixa %s, %s, vence todo dia %d (%s)%s.',
            $payload['name'],
            self::amountLabel($amount, $varies),
            $payload['due_day'],
            $category?->name ?? 'sem categoria',
            $payload['autopay'] ? ', no débito automático' : '',
        ));
    }

    /** As regras do "Cadastrar conta" da Agenda. */
    public function validatePayload(array $payload, ToolContext $context): array
    {
        return RequestRules::validate(StoreBillRequest::class, $payload);
    }

    public function card(array $payload, ?array $result, ToolContext $context): array
    {
        return [
            'type' => 'bill_created',
            'bill' => [
                'id' => $result['bill_id'] ?? null,
                'name' => $payload['name'],
                'expected_cents' => $payload['expected_cents'] ?? null,
                'amount_varies' => (bool) ($payload['amount_varies'] ?? false),
                'due_day' => $payload['due_day'],
                'autopay' => (bool) ($payload['autopay'] ?? false),
                'category' => self::categoryCard(Category::query()->find($payload['category_id'])),
                // O primeiro vencimento: neste mês, se o dia ainda não passou; senão, no próximo.
                'first_due_on' => $result['first_due_on'] ?? null,
            ],
        ];
    }

    public function execute(array $payload, ToolContext $context): array
    {
        $bill = $this->bills->create($context->user, $payload, $context->today);

        return ['bill_id' => $bill->id, 'first_due_on' => Month::day($bill->starts_on, $bill->due_day)->toDateString()];
    }

    /** Desfazer logo depois de cadastrar: a conta some de vez (ainda não há meses pagos nela). */
    public function undo(array $result, ToolContext $context): void
    {
        RecurringBill::query()->whereKey($result['bill_id'] ?? null)->first()?->delete();
    }

    /** "R$ 99,90", "valor variável (~R$ 170,00)". */
    private static function amountLabel(?int $amount, bool $varies): string
    {
        if (! $varies) {
            return self::money((int) $amount);
        }

        return $amount !== null ? 'valor variável (~'.self::money($amount).')' : 'valor variável';
    }
}
