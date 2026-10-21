<?php

namespace Database\Seeders;

use App\Enums\HouseholdRole;
use App\Models\Card;
use App\Models\Category;
use App\Models\Household;
use App\Models\HouseholdList;
use App\Models\RecurringBill;
use App\Models\User;
use App\Services\BillService;
use App\Services\BudgetService;
use App\Services\HouseholdService;
use App\Services\InstallmentService;
use App\Services\ListService;
use App\Services\ReminderService;
use App\Services\TransactionService;
use App\Support\HouseholdContext;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Uma casa de exemplo com 3 meses de uso, relativa à data de hoje, para desenvolver e mostrar as
 * telas. Usa os mesmos serviços da API, então faturas, parcelas e contas seguem as mesmas regras.
 * Os valores saem de um sorteio com semente fixa: no mesmo dia, rodar de novo gera os mesmos dados.
 *
 * Login: leo@example.com (dono) e ana@example.com (membro), senha "password".
 */
class DemoHouseholdSeeder extends Seeder
{
    private const TIMEZONE = 'America/Sao_Paulo';

    private CarbonImmutable $today;

    private Household $house;

    private User $leo;

    private User $ana;

    private Card $nubank;

    private Card $inter;

    /** @var array<string, Category> */
    private array $categories = [];

    public function __construct(
        private readonly HouseholdService $households,
        private readonly TransactionService $transactions,
        private readonly InstallmentService $installments,
        private readonly BillService $bills,
        private readonly BudgetService $budgets,
        private readonly ListService $lists,
        private readonly ReminderService $reminders,
        private readonly HouseholdContext $context,
    ) {}

    public function run(): void
    {
        mt_srand(20261008);
        $this->today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();

        $this->leo = User::create(['name' => 'Leo', 'email' => 'leo@example.com', 'password' => 'password']);
        $this->ana = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'password']);

        // As contas de exemplo já entram liberadas (sem passar pela aprovação do administrador).
        foreach ([$this->leo, $this->ana] as $user) {
            $user->forceFill(['approved_at' => now()])->save();
        }

        $this->house = $this->households->createFor($this->leo, 'Casa do Leo');
        $this->households->addMember($this->house, $this->ana, HouseholdRole::Member);

        // Os serviços leem a casa do contexto, como numa requisição do Leo.
        $this->context->set($this->house, HouseholdRole::Owner, $this->leo);

        $this->cards();
        $this->everydayTransactions();
        $this->installmentPurchase();
        $this->recurringBills();
        $this->setBudgets();
        $this->fillLists();
        $this->createReminders();
    }

    private function cards(): void
    {
        $this->nubank = new Card(['name' => 'Nubank', 'closing_day' => 3, 'due_day' => 10, 'limit_cents' => 800000, 'color' => '#7E5490']);
        $this->nubank->user()->associate($this->leo)->save();
        $this->nubank->makeDefault();

        $this->inter = new Card(['name' => 'Inter', 'closing_day' => 25, 'due_day' => 5, 'limit_cents' => 400000, 'color' => '#C27A3A']);
        $this->inter->user()->associate($this->ana)->save();
        $this->inter->makeDefault();
    }

    /** Salários e gastos do dia a dia dos últimos 3 meses (só até hoje), divididos entre Leo e Ana. */
    private function everydayTransactions(): void
    {
        foreach ($this->months() as $month) {
            $this->income($this->leo, 'Salário', 540000, Month::day($month, 5), 'Salário');
            $this->income($this->ana, 'Salário', 380000, Month::day($month, 5), 'Salário');

            if (mt_rand(0, 1)) {
                $this->income($this->leo, 'Renda extra', mt_rand(300, 900) * 100, $this->randomDay($month), 'Freela');
            }

            $this->some($month, 'Mercado', ['Mercado', 'Feira', 'Padaria', 'Hortifruti'], 4, 6, 6000, 26000);
            $this->some($month, 'Alimentação', ['Café', 'Almoço', 'Lanche'], 6, 10, 1500, 5500);
            $this->some($month, 'Delivery', ['Pizza', 'Hambúrguer', 'Japonês', 'Açaí'], 4, 7, 3500, 7500);
            $this->some($month, 'Transporte', ['Corrida de app', 'Combustível', 'Estacionamento'], 6, 10, 1200, 4500);
            $this->some($month, 'Lazer', ['Cinema', 'Bar', 'Show'], 1, 3, 4000, 18000);
            $this->some($month, 'Saúde', ['Farmácia'], 0, 2, 3000, 12000);

            $this->expense($this->leo, 'Assinaturas', 3990, Month::day($month, 15), 'Streaming de vídeo', payment: 'credit_card');
            $this->expense($this->ana, 'Assinaturas', 2190, Month::day($month, 20), 'Streaming de música', payment: 'credit_card');

            // Gastos privados da Ana: o Leo não vê, nem nos totais.
            $this->some($month, 'Compras', ['Presente', 'Roupa'], 1, 2, 5000, 20000, $this->ana, 'private');
        }

        // Garante o orçamento de delivery estourado no mês atual, como no esboço do app.
        $current = $this->today->startOfMonth();
        foreach ([4200, 5800, 3900, 6100, 4700, 5300, 4400] as $index => $cents) {
            $day = min($this->today->day, 1 + $index * 3);
            $this->expense($this->leo, 'Delivery', $cents, Month::day($current, $day), 'Pizza', payment: 'pix');
        }
    }

    /** A TV do esboço: 10x de R$ 250 no Nubank, comprada no mês passado. */
    private function installmentPurchase(): void
    {
        $this->installments->create($this->leo, [
            'description' => 'TV',
            'category_id' => $this->category('Compras')->id,
            'card_id' => $this->nubank->id,
            'installments' => 10,
            'installment_cents' => 25000,
            'purchased_on' => Month::day($this->today->startOfMonth()->subMonth(), 7)->toDateString(),
        ]);
    }

    /** Contas fixas desde 2 meses atrás. O condomínio do mês passado fica sem pagar (atrasado). */
    private function recurringBills(): void
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $start = $this->months()[0]->format('Y-m');

        $bill = fn (string $name, string $category, ?int $expected, int $dueDay, bool $autopay = false, bool $varies = false) => $this->bills->create(
            $this->leo,
            [
                'name' => $name,
                'category_id' => $this->category($category)->id,
                'expected_cents' => $expected,
                'amount_varies' => $varies,
                'due_day' => $dueDay,
                'autopay' => $autopay,
                'starts_on' => $start,
            ],
            $now,
        );

        $rent = $bill('Aluguel', 'Moradia', 180000, 5);
        $condo = $bill('Condomínio', 'Moradia', 62000, 10);
        $internet = $bill('Internet', 'Contas da casa', 9990, 15, autopay: true);
        // A luz muda todo mês: começa com a estimativa de R$ 170 e passa a usar a média dos pagamentos.
        $power = $bill('Luz', 'Contas da casa', 17000, 8, varies: true);

        [$twoMonthsAgo, $lastMonth, $thisMonth] = $this->months();

        foreach ([$twoMonthsAgo, $lastMonth, $thisMonth] as $month) {
            $this->payIfDue($rent, $month, $now);
            $this->payIfDue($internet, $month, $now);
        }

        $this->payIfDue($condo, $twoMonthsAgo, $now);
        $this->payIfDue($power, $twoMonthsAgo, $now, mt_rand(160, 220) * 100);
        $this->payIfDue($power, $lastMonth, $now, mt_rand(160, 220) * 100);
    }

    private function setBudgets(): void
    {
        foreach (['Mercado' => 80000, 'Delivery' => 30000, 'Transporte' => 25000, 'Lazer' => 40000] as $category => $limit) {
            $this->budgets->set($this->category($category), $limit);
        }
    }

    private function fillLists(): void
    {
        $market = HouseholdList::query()->where('name', 'Mercado')->firstOrFail();
        $wishes = HouseholdList::query()->where('name', 'Desejos')->firstOrFail();
        $home = HouseholdList::query()->where('name', 'Casa')->firstOrFail();

        $this->lists->addItems($market, $this->leo, [
            ['text' => 'Leite', 'quantity' => '2'],
            ['text' => 'Café'],
            ['text' => 'Sabão em pó'],
            ['text' => 'Arroz', 'quantity' => '5 kg'],
            ['text' => 'Banana', 'quantity' => '1 dúzia'],
            ['text' => 'Pão'],
        ]);

        // A Ana já pegou dois itens no mercado.
        $picked = $market->items()->whereIn('text', ['Banana', 'Pão'])->pluck('id')->all();
        $this->lists->check($market, $this->ana, $picked);

        $this->lists->addItems($wishes, $this->leo, [
            ['text' => 'Fone de ouvido', 'price_cents' => 34990],
            ['text' => 'Air fryer', 'price_cents' => 49900],
            ['text' => 'Livro de receitas', 'price_cents' => 5990],
        ]);

        $this->lists->addItems($home, $this->ana, [
            ['text' => 'Trocar a resistência do chuveiro'],
            ['text' => 'Consertar a porta do armário', 'due_on' => $this->today->addDays(10)->toDateString()],
            ['text' => 'Pintar o quarto'],
        ]);

        // Uma lista privada da Ana, que o Leo não vê.
        $this->actingAs($this->ana, HouseholdRole::Member, function () {
            $gifts = $this->lists->create($this->ana, ['name' => 'Presentes', 'kind' => 'wishlist', 'visibility' => 'private']);
            $this->lists->addItems($gifts, $this->ana, [['text' => 'Relógio para o Leo', 'price_cents' => 45000]]);
        });
    }

    private function createReminders(): void
    {
        $ipva = CarbonImmutable::create($this->today->year, 1, 15, 0, 0, 0, self::TIMEZONE);
        if ($ipva->lessThanOrEqualTo($this->today)) {
            $ipva = $ipva->addYear();
        }

        $this->reminders->create($this->leo, [
            'text' => 'Pagar o IPVA',
            'remind_at' => $ipva->toDateString(),
            'recurrence' => 'yearly',
            'visibility' => 'household',
        ]);

        $this->reminders->create($this->leo, [
            'text' => 'Trocar o filtro de água',
            'remind_at' => $this->today->addMonths(3)->toDateString(),
            'recurrence' => 'monthly',
            'every' => 6,
        ]);

        $this->reminders->create($this->ana, [
            'text' => 'Vacina do Rex',
            'remind_at' => $this->today->addDays(20)->toDateString().' 10:00',
            'recurrence' => 'yearly',
            'visibility' => 'household',
        ]);
    }

    /** Entre $min e $max gastos sorteados no mês, cada um de Leo (2/3 das vezes) ou da Ana. */
    private function some(
        CarbonImmutable $month,
        string $category,
        array $descriptions,
        int $min,
        int $max,
        int $minCents,
        int $maxCents,
        ?User $who = null,
        string $visibility = 'household',
    ): void {
        $times = mt_rand($min, $max);

        for ($i = 0; $i < $times; $i++) {
            $this->expense(
                $who ?? (mt_rand(0, 2) === 0 ? $this->ana : $this->leo),
                $category,
                mt_rand(intdiv($minCents, 100), intdiv($maxCents, 100)) * 100 + mt_rand(0, 99),
                $this->randomDay($month),
                $descriptions[mt_rand(0, count($descriptions) - 1)],
                $visibility,
            );
        }
    }

    /** Lança uma despesa, se a data não estiver no futuro. Sem forma de pagamento, sorteia uma. */
    private function expense(
        User $who,
        string $category,
        int $cents,
        CarbonImmutable $date,
        string $description,
        string $visibility = 'household',
        ?string $payment = null,
    ): void {
        if ($date->greaterThan($this->today)) {
            return;
        }

        $payment ??= ['pix', 'debit', 'credit_card'][mt_rand(0, 2)];
        $card = $who->is($this->leo) ? $this->nubank : $this->inter;

        $this->transactions->create($who, [
            'kind' => 'expense',
            'amount_cents' => $cents,
            'description' => $description,
            'occurred_on' => $date->toDateString(),
            'category_id' => $this->category($category)->id,
            'payment_type' => $payment,
            'card_id' => $payment === 'credit_card' ? $card->id : null,
            'visibility' => $visibility,
        ]);
    }

    private function income(User $who, string $category, int $cents, CarbonImmutable $date, string $description): void
    {
        if ($date->greaterThan($this->today)) {
            return;
        }

        $this->transactions->create($who, [
            'kind' => 'income',
            'amount_cents' => $cents,
            'description' => $description,
            'occurred_on' => $date->toDateString(),
            'category_id' => $this->category($category, 'income')->id,
        ]);
    }

    /** Paga o mês da conta no dia do vencimento, se ele já chegou. */
    private function payIfDue(RecurringBill $bill, CarbonImmutable $month, CarbonImmutable $now, ?int $amount = null): void
    {
        $dueOn = Month::day($month, $bill->due_day);

        if ($dueOn->greaterThan($this->today)) {
            return;
        }

        $this->bills->pay($bill, $this->leo, [
            'month' => $month->format('Y-m'),
            'paid_on' => $dueOn->toDateString(),
            'amount_cents' => $amount,
            'payment_type' => 'pix',
        ], $now);
    }

    /** Roda $callback como outro membro da casa (muda quem o contexto considera o usuário). */
    private function actingAs(User $user, HouseholdRole $role, callable $callback): void
    {
        $this->context->set($this->house, $role, $user);
        $callback();
        $this->context->set($this->house, HouseholdRole::Owner, $this->leo);
    }

    private function category(string $name, string $kind = 'expense'): Category
    {
        return $this->categories["{$kind}.{$name}"] ??= Category::query()
            ->where('kind', $kind)
            ->where('name', $name)
            ->firstOrFail();
    }

    /** @return list<CarbonImmutable> o 1º dia de cada um dos últimos 3 meses, do mais antigo ao atual */
    private function months(): array
    {
        $current = $this->today->startOfMonth();

        return [$current->subMonths(2), $current->subMonth(), $current];
    }

    private function randomDay(CarbonImmutable $month): CarbonImmutable
    {
        return Month::day($month, mt_rand(1, $month->daysInMonth));
    }
}
