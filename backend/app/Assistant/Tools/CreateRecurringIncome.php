<?php

namespace App\Assistant\Tools;

use App\Assistant\Actions\ActionToolBase;
use App\Assistant\Actions\PreparedAction;
use App\Assistant\Actions\RequestRules;
use App\Assistant\NameMatcher;
use App\Enums\CategoryKind;
use App\Http\Requests\Finance\StoreRecurringIncomeRequest;
use App\Models\Category;
use App\Models\RecurringIncome;
use App\Models\Transaction;
use App\Services\RecurringIncomeService;

/**
 * "Recebo 5 mil de salário todo dia 5". Cadastra a receita fixa como o "Adicionar" da Agenda: ela
 * começa no próximo dia que cair e é lançada sozinha todo mês. Grava na hora, com "Desfazer".
 */
final class CreateRecurringIncome extends ActionToolBase
{
    public function __construct(private readonly RecurringIncomeService $incomes) {}

    public function name(): string
    {
        return 'create_recurring_income';
    }

    public function description(): string
    {
        return 'Cadastra uma receita fixa, que cai todo mês no mesmo dia e é lançada sozinha ("recebo 5 mil de salário '
            .'todo dia 5"). Começa no próximo dia que cair; se o dia é hoje, a de hoje já entra. Grava na hora e o '
            .'card tem "Desfazer".';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'description' => ['type' => 'string', 'description' => 'Curta: "Salário", "Aluguel do quarto".'],
                'amount' => ['type' => 'number', 'description' => 'O valor em reais.'],
                'day_of_month' => ['type' => 'integer', 'description' => 'O dia em que cai (1 a 31).'],
                'category' => ['type' => 'string', 'description' => 'Nome da categoria de receita. Sem certeza, deixe vazio.'],
                'private' => ['type' => 'boolean', 'description' => 'Só quem pediu vê. Padrão: a casa toda vê.'],
            ],
            'required' => ['description', 'amount', 'day_of_month'],
        ];
    }

    public function tier(): ToolTier
    {
        return ToolTier::Immediate;
    }

    public function status(): string
    {
        return 'Cadastrando a receita…';
    }

    protected function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000000'],
            'day_of_month' => ['required', 'integer', 'between:1,31'],
            'category' => ['nullable', 'string', 'max:60'],
            'private' => ['nullable', 'boolean'],
        ];
    }

    public function prepare(array $args, ToolContext $context): PreparedAction|ToolResult
    {
        $description = self::capitalize($args['description']);
        $existing = RecurringIncome::query()->active()->get()
            ->first(fn (RecurringIncome $income) => NameMatcher::normalize($income->description) === NameMatcher::normalize($description));

        if ($existing) {
            return ToolResult::error("Já existe a receita fixa {$existing->description}. Para mudar o valor ou o dia, é pela Agenda, em Contas.");
        }

        $categories = Category::query()->active()->where('kind', CategoryKind::Income)->orderBy('position')->orderBy('id')->get();
        // Sem categoria dita, a que aparece na descrição ("salário" cai em Salário); senão, Outros.
        $category = self::pickCategory($categories, $args['category'] ?? NameMatcher::mentioned($categories->pluck('name')->all(), $description), ['Outros']);

        $payload = [
            'description' => $description,
            'amount_cents' => self::cents($args['amount']),
            'category_id' => $category?->id,
            'day_of_month' => (int) $args['day_of_month'],
            'visibility' => ($args['private'] ?? false) ? 'private' : 'household',
        ];

        $first = $this->incomes->firstDate($payload['day_of_month'], $context->today);

        return new PreparedAction($payload, sprintf(
            'Pronto: receita fixa %s, %s todo dia %d. %s',
            $description,
            self::money($payload['amount_cents']),
            $payload['day_of_month'],
            $first->isSameDay($context->today) ? 'Como o dia é hoje, a de hoje já entrou.' : 'A primeira cai em '.self::shortDate($first).'.',
        ));
    }

    /** As regras do "Adicionar" da Agenda. */
    public function validatePayload(array $payload, ToolContext $context): array
    {
        return RequestRules::validate(StoreRecurringIncomeRequest::class, $payload);
    }

    public function card(array $payload, ?array $result, ToolContext $context): array
    {
        return [
            'type' => 'income_created',
            'income' => [
                'id' => $result['income_id'] ?? null,
                'description' => $payload['description'],
                'amount_cents' => $payload['amount_cents'],
                'day_of_month' => $payload['day_of_month'],
                'first_on' => $result['first_on'] ?? null,
                'category' => self::categoryCard(Category::query()->find($payload['category_id'])),
                'visibility' => $payload['visibility'] ?? 'household',
            ],
            // A de hoje já entrou (o dia era hoje).
            'launched_today' => ($result['launched'] ?? []) !== [],
        ];
    }

    public function execute(array $payload, ToolContext $context): array
    {
        $first = $this->incomes->firstDate((int) $payload['day_of_month'], $context->today);
        $income = $this->incomes->create($context->user, $payload, $context->today);

        return [
            'income_id' => $income->id,
            'first_on' => $first->toDateString(),
            // O que já foi lançado ao cadastrar (só quando o dia é hoje), para o "Desfazer" levar junto.
            'launched' => Transaction::query()->where('recurring_income_id', $income->id)->pluck('id')->all(),
        ];
    }

    public function undo(array $result, ToolContext $context): void
    {
        Transaction::query()->whereKey($result['launched'] ?? [])->delete();
        RecurringIncome::query()->whereKey($result['income_id'] ?? null)->delete();
    }
}
