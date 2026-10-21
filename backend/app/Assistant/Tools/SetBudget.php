<?php

namespace App\Assistant\Tools;

use App\Assistant\Actions\ActionToolBase;
use App\Assistant\Actions\PreparedAction;
use App\Assistant\Actions\RequestRules;
use App\Assistant\NameMatcher;
use App\Enums\CategoryKind;
use App\Http\Requests\Finance\UpsertBudgetRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Services\BudgetService;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * "Limite de 800 no mercado", "orçamento de 300 pro delivery". Cria ou muda o orçamento da
 * categoria na hora; o "Desfazer" volta o limite que havia antes (ou tira o orçamento, se não havia).
 */
final class SetBudget extends ActionToolBase
{
    public function __construct(private readonly BudgetService $budgets) {}

    public function name(): string
    {
        return 'set_budget';
    }

    public function description(): string
    {
        return 'Define ou muda o limite de gastos por mês de uma categoria de despesa ("limite de 800 no mercado", '
            .'"orçamento de 300 pro delivery"). Grava na hora e o card tem "Desfazer", que volta o limite anterior.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'category' => ['type' => 'string', 'description' => 'Nome da categoria de despesa, como na lista.'],
                'amount' => ['type' => 'number', 'description' => 'O limite por mês, em reais.'],
            ],
            'required' => ['category', 'amount'],
        ];
    }

    public function tier(): ToolTier
    {
        return ToolTier::Immediate;
    }

    public function status(): string
    {
        return 'Ajustando o orçamento…';
    }

    protected function rules(): array
    {
        return [
            'category' => ['required', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000000'],
        ];
    }

    public function prepare(array $args, ToolContext $context): PreparedAction|ToolResult
    {
        $categories = Category::query()->active()->where('kind', CategoryKind::Expense)->with('budget')->orderBy('position')->orderBy('id')->get();
        $category = NameMatcher::find($categories, $args['category'], fn (Category $item) => $item->name);

        if (! $category) {
            return ToolResult::error(sprintf(
                'Não encontrei a categoria "%s". As categorias de despesa são: %s.',
                $args['category'],
                $categories->pluck('name')->join(', '),
            ));
        }

        $limit = self::cents($args['amount']);
        $previous = $category->budget?->limit_cents;

        if ($previous === $limit) {
            return ToolResult::error(sprintf('O orçamento de %s já é %s por mês.', $category->name, self::money($limit)));
        }

        $spent = $this->budgets->detail($category, $context->today)['spent_cents'];

        return new PreparedAction(['category_id' => $category->id, 'limit_cents' => $limit], sprintf(
            'Pronto: orçamento de %s em %s por mês%s. Este mês já foram %s.',
            $category->name,
            self::money($limit),
            $previous !== null ? ' (antes era '.self::money($previous).')' : '',
            self::money($spent),
        ));
    }

    /** As regras do orçamento da tela (UpsertBudgetRequest), mais a categoria, que precisa ser de despesa e ativa. */
    public function validatePayload(array $payload, ToolContext $context): array
    {
        $categoryRule = function (string $attribute, mixed $value, Closure $fail) {
            if (! Category::query()->active()->where('kind', CategoryKind::Expense)->whereKey($value)->exists()) {
                $fail(__('assistant.actions.gone'));
            }
        };

        Validator::make($payload, ['category_id' => ['bail', 'required', 'integer', $categoryRule]])->validate();

        return [
            'category_id' => (int) $payload['category_id'],
            ...RequestRules::validate(UpsertBudgetRequest::class, ['limit_cents' => $payload['limit_cents'] ?? null]),
        ];
    }

    public function card(array $payload, ?array $result, ToolContext $context): array
    {
        $category = Category::query()->with('budget')->find($payload['category_id']);

        return [
            'type' => 'budget_set',
            'category' => self::categoryCard($category),
            'limit_cents' => $payload['limit_cents'],
            // O limite que havia antes (null: a categoria não tinha orçamento).
            'previous_cents' => $result['previous_cents'] ?? null,
            'spent_cents' => $category ? $this->budgets->detail($category, $context->today)['spent_cents'] : 0,
        ];
    }

    public function execute(array $payload, ToolContext $context): array
    {
        $category = Category::query()->active()->with('budget')->find($payload['category_id'])
            ?? throw ValidationException::withMessages(['action' => __('assistant.actions.gone')]);

        $previous = $category->budget?->limit_cents;
        $this->budgets->set($category, $payload['limit_cents']);

        return ['category_id' => $category->id, 'previous_cents' => $previous];
    }

    public function undo(array $result, ToolContext $context): void
    {
        $category = Category::query()->find($result['category_id'] ?? null);

        if ($category === null) {
            return;
        }

        if (($result['previous_cents'] ?? null) === null) {
            Budget::query()->where('category_id', $category->id)->delete();
        } else {
            $this->budgets->set($category, $result['previous_cents']);
        }
    }
}
