<?php

namespace App\Assistant;

use App\Enums\HouseholdRole;
use App\Models\Budget;
use App\Models\Card;
use App\Models\Category;
use App\Models\Household;
use App\Models\HouseholdList;
use App\Models\RecurringBill;
use App\Models\RecurringIncome;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * O que a IA precisa saber para entender a frase: que dia é hoje, com quem fala e os nomes que a
 * casa usa. Sem isso ela não sabe o que é "no cartão", "semana passada" ou "na lista de desejos".
 */
final readonly class AssistantContext
{
    /**
     * @param  list<array{name: string, kind: string}>  $categories  na ordem escolhida pela casa
     * @param  list<array{name: string, owner: string, is_mine: bool, is_default: bool}>  $cards
     * @param  list<array{name: string, kind: string, is_default: bool}>  $lists
     * @param  list<array{name: string, amount_varies: bool}>  $bills  as contas fixas ("paguei a luz")
     * @param  list<array{category: string, limit: string}>  $budgets  os limites por mês ("limite de 800 no mercado")
     * @param  list<array{description: string, day_of_month: int}>  $incomes  as receitas fixas (salário)
     */
    public function __construct(
        // Agora, no fuso de quem pergunta.
        public CarbonImmutable $today,
        public string $userName,
        public string $householdName,
        public HouseholdRole $role,
        public array $categories = [],
        public array $cards = [],
        public array $lists = [],
        public array $bills = [],
        public array $budgets = [],
        public array $incomes = [],
    ) {}

    /** Monta o contexto da casa atual. O HouseholdContext precisa estar preenchido (filtros de casa e privados). */
    public static function for(User $user, Household $household, HouseholdRole $role, CarbonImmutable $today): self
    {
        $categories = Category::query()->active()->orderBy('kind')->orderBy('position')->orderBy('id')->get()
            ->map(fn (Category $category) => ['name' => $category->name, 'kind' => $category->kind->value])
            ->all();

        $cards = Card::query()->active()->with('user')->orderBy('name')->get()
            ->map(fn (Card $card) => [
                'name' => $card->name,
                'owner' => $card->user->name,
                'is_mine' => $card->user_id === $user->id,
                'is_default' => (bool) $card->is_default,
            ])
            ->all();

        $lists = HouseholdList::query()->orderBy('position')->orderBy('id')->get()
            ->map(fn (HouseholdList $list) => ['name' => $list->name, 'kind' => $list->kind->value, 'is_default' => $list->is_default])
            ->all();

        $bills = RecurringBill::query()->active()->orderBy('name')->get()
            ->map(fn (RecurringBill $bill) => ['name' => $bill->name, 'amount_varies' => $bill->amount_varies])
            ->all();

        $budgets = Budget::query()->with('category')->get()
            ->filter(fn (Budget $budget) => $budget->category?->archived_at === null)
            ->sortBy(fn (Budget $budget) => $budget->category->position)
            ->map(fn (Budget $budget) => ['category' => $budget->category->name, 'limit' => Money::format($budget->limit_cents)])
            ->values()
            ->all();

        $incomes = RecurringIncome::query()->active()->orderBy('day_of_month')->orderBy('description')->get()
            ->map(fn (RecurringIncome $income) => ['description' => $income->description, 'day_of_month' => $income->day_of_month])
            ->all();

        return new self($today, $user->name, $household->name, $role, $categories, $cards, $lists, $bills, $budgets, $incomes);
    }

    /** Os nomes das categorias de um tipo ("expense" ou "income"). */
    public function categoryNames(string $kind): array
    {
        return array_values(array_map(
            fn (array $category) => $category['name'],
            array_filter($this->categories, fn (array $category) => $category['kind'] === $kind),
        ));
    }
}
