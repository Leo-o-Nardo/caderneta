<?php

namespace App\Assistant\Llm\Fake;

use App\Assistant\AssistantContext;
use App\Assistant\Llm\ToolCall;
use App\Assistant\NameMatcher;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * As regras da IA simulada: reconhece as frases mais comuns e escolhe a ferramenta e os argumentos,
 * como a IA real faria. Não precisa entender tudo; a ideia é cobrir as frases do esboço e do demo.
 */
final class FakeIntents
{
    public static function match(string $text, AssistantContext $context): ?ToolCall
    {
        $t = NameMatcher::normalize($text);

        // As ações primeiro: "gastei 12 de café" grava; "quanto gastei" pergunta.
        return self::action($text, $t, $context) ?? self::query($t, $context);
    }

    /**
     * Lançar, corrigir, pagar conta, apagar, mexer nas listas, criar lembrete e cadastrar contas,
     * receitas fixas e orçamentos. $text é a frase original (com acentos e maiúsculas, para "Café" e
     * "IPVA" chegarem como foram ditos); $t, a normalizada. A ordem importa: as frases mais
     * específicas vêm antes, para não caírem no "gastei" ou nas perguntas.
     */
    private static function action(string $text, string $t, AssistantContext $context): ?ToolCall
    {
        $today = $context->today;
        $cards = array_column($context->cards, 'name');
        $lists = array_column($context->lists, 'name');
        $amounts = FakeParser::amounts($text);
        $day = FakeParser::dayOfMonth($text);

        // "apaga a lista de churrasco".
        if (preg_match('/^(apaga|apague|exclui|exclua|deleta|delete|remove|remova)\s+(a\s+)?listas?\b/', $t)) {
            return self::call('delete_list', ['list' => FakeParser::listName($text)]);
        }

        // "apaga o uber de ontem" ("tira o leite da lista" não é apagar lançamento).
        if (preg_match('/^(apaga|apague|exclui|exclua|deleta|delete|remove|remova)\b/', $t) && ! preg_match('/\blistas?\b/', $t)) {
            return self::call('delete_transaction', [
                'description' => FakeParser::deletionSubject($text),
                'date' => FakeParser::date($text, $today),
                'amount' => $amounts[0] ?? null,
            ]);
        }

        // "me lembra de pagar o IPVA dia 20".
        if (preg_match('/\b(me lembra|me lembre|lembra de|lembre de|lembre-me|cria um lembrete|criar um lembrete)\b/', $t)) {
            return self::call('create_reminder', FakeParser::reminder($text, $today));
        }

        // "cria uma lista de churrasco com carne, carvão e gelo".
        if ((preg_match('/^(me )?(cria|crie|criar|faz|faca|monta|monte|abre|abra)\b/', $t) && preg_match('/\blistas?\b/', $t)) || preg_match('/^(uma )?nova lista\b/', $t)) {
            $args = FakeParser::newList($text);

            if (isset($args['name'])) {
                return self::call('create_list', $args);
            }
        }

        // "limite de 800 no mercado", "orçamento de 300 pro delivery" (sem valor, é pergunta).
        $budgetCategory = NameMatcher::mentioned($context->categoryNames('expense'), $t);

        if ($amounts !== [] && $budgetCategory !== null && preg_match('/\b(limite|orcamento)\b/', $t)) {
            return self::call('set_budget', ['category' => $budgetCategory, 'amount' => $amounts[0]]);
        }

        // "recebo 5 mil de salário todo dia 5", "meu salário de 5.000 cai todo dia 5". "Recebi 5 mil
        // de salário dia 5", no passado, é um lançamento só (cai no "recebi", mais abaixo).
        $receives = preg_match('/\b(recebo|ganho)\b/', $t)
            || (preg_match('/\b(salario|renda)\b/', $t) && preg_match('/\btod[oa]s?\b/', $t) && ! preg_match('/\b(vence|paguei|pagamos|recebi|ganhei)\b/', $t));

        if ($day !== null && $amounts !== [] && $receives) {
            return self::call('create_recurring_income', FakeParser::recurringIncome($text, $context));
        }

        // "cadastra a internet de 99,90 todo dia 10", "a água vence dia 20 e o valor muda".
        $registersBill = preg_match('/\bvence\b/', $t)
            || preg_match('/^(me )?(cadastra|cadastre|registra|registre)\b/', $t)
            || str_contains($t, 'conta fixa');

        if ($day !== null && $registersBill && ! preg_match('/\b(paguei|pagamos|gastei|gastamos|comprei|recebi)\b/', $t)) {
            $args = FakeParser::recurringBill($text);

            if (isset($args['name'])) {
                return self::call('create_recurring_bill', $args);
            }
        }

        // "o café de ontem foi 15, não 12", "corrige o uber para 25", "muda a categoria do uber para lazer".
        $corrects = (preg_match('/^(corrige|corrija|muda|mude|altera|altere|troca|troque|arruma|arrume|acerta|acerte)\b/', $t)
                && ! preg_match('/\b(listas?|orcamento|limite|lembretes?)\b/', $t))
            || preg_match('/^(o|a|os|as)\s.+\s(foi|era)\s/', $t);

        if ($corrects) {
            $args = FakeParser::transactionUpdate($text, $context);
            $changes = array_intersect_key($args, array_flip(['new_amount', 'new_description', 'new_category', 'new_date', 'new_payment', 'new_card']));

            // Sem nada que mude ("a fatura foi paga?"), não é correção: segue para as perguntas.
            if (isset($args['description']) && $changes !== []) {
                return self::call('update_transaction', $args);
            }
        }

        // "coloca leite, café e pão na lista".
        if (preg_match('/^(me )?(coloca|coloque|adiciona|adicione|bota|bote|poe|ponha|inclui|inclua|anota|anote)\b/', $t) && preg_match('/\blistas?\b/', $t)) {
            return self::call('add_list_items', [
                'list' => NameMatcher::mentioned($lists, $t),
                'items' => array_map(fn (string $item) => ['text' => $item], FakeParser::listItems($text)),
            ]);
        }

        // "peguei o leite e o pão" (e "comprei o leite", sem valor: com valor é um gasto).
        if (preg_match('/^(ja )?(peguei|pegamos|marca|marque|risca|risque)\b/', $t) || (preg_match('/^(comprei|compramos)\b/', $t) && $amounts === [])) {
            return self::call('check_list_items', [
                'list' => NameMatcher::mentioned($lists, $t),
                'items' => FakeParser::checkedItems($text),
            ]);
        }

        // "paguei a luz", "paguei 182,40 de luz no pix".
        $bill = NameMatcher::mentioned(array_column($context->bills, 'name'), $t);

        if ($bill !== null && preg_match('/\b(paguei|pagamos)\b/', $t)) {
            return self::call('mark_bill_paid', [
                'bill' => $bill,
                'amount' => $amounts[0] ?? null,
                'date' => FakeParser::date($text, $today),
                'payment' => FakeParser::payment($text),
                'card' => NameMatcher::mentioned($cards, $t),
            ]);
        }

        // "gastei 12 de café e 30 de uber", "recebi 8.500 de salário", "comprei uma TV de 2.500 em 10x".
        if (! str_contains($t, 'quanto') && $amounts !== [] && preg_match('/\b(gastei|gastamos|paguei|pagamos|comprei|compramos|recebi|recebemos|ganhei|ganhamos)\b/', $t)) {
            $income = preg_match('/\b(recebi|recebemos|ganhei|ganhamos)\b/', $t) === 1;
            $items = FakeParser::transactions($text, $context, $income ? 'income' : 'expense');

            return $items === [] ? null : self::call('create_transactions', ['items' => $items]);
        }

        return null;
    }

    /** As perguntas: quanto gastei, como está o mês, fatura, contas, orçamentos, listas, lembretes. */
    private static function query(string $t, AssistantContext $context): ?ToolCall
    {
        $today = $context->today;
        $cards = array_column($context->cards, 'name');

        if (str_contains($t, 'fatura')) {
            return self::call('get_card_invoice', [
                'card' => NameMatcher::mentioned($cards, $t),
                'month' => self::month($t, $today),
            ]);
        }

        if (preg_match('/\b(recebi|recebemos|ganhei|ganhamos|entrou|entraram)\b/', $t)) {
            return self::spending($t, $context, 'income');
        }

        if (preg_match('/\b(gastei|gastamos|gastou|gastaram|gasto|gastos|despesas?)\b/', $t)) {
            return self::spending($t, $context, 'expense');
        }

        if (str_contains($t, 'orcamento')) {
            return self::call('get_budget_status', ['month' => self::month($t, $today)]);
        }

        if (preg_match('/\b(como (esta|ta|anda|foi|fica)|resumo|saldo|balanco)\b/', $t)) {
            return self::call('get_month_overview', ['month' => self::month($t, $today)]);
        }

        if (preg_match('/\b(contas?|boletos?)\b/', $t)) {
            return self::call('list_bills', ['month' => self::month($t, $today)]);
        }

        if (preg_match('/\blistas?\b/', $t)) {
            return self::call('get_list', ['list' => NameMatcher::mentioned(array_column($context->lists, 'name'), $t)]);
        }

        if (preg_match('/\b(lembretes?|lembrar)\b/', $t)) {
            return self::call('list_reminders', []);
        }

        return null;
    }

    /** "quanto gastei com mercado semana passada", "quanto recebi em setembro". */
    private static function spending(string $t, AssistantContext $context, string $kind): ToolCall
    {
        [$from, $to] = self::period($t, $context->today);
        $category = NameMatcher::mentioned($context->categoryNames($kind), $t);

        $groupBy = match (true) {
            str_contains($t, 'por dia') => 'day',
            str_contains($t, 'por mes') => 'month',
            (bool) preg_match('/\bpor (forma|cartao|pagamento)/', $t) => 'payment',
            // Sem categoria, a resposta mostra onde o dinheiro foi (e vira gráfico).
            $category === null => 'category',
            default => null,
        };

        return self::call('query_transactions', [
            'from' => $from,
            'to' => $to,
            'kind' => $kind,
            'category' => $category,
            'card' => NameMatcher::mentioned(array_column($context->cards, 'name'), $t),
            'group_by' => $groupBy,
        ]);
    }

    /**
     * O período citado na frase. Sem nenhum, o mês atual até hoje.
     *
     * @return array{0: string, 1: string}
     */
    private static function period(string $t, CarbonImmutable $today): array
    {
        $today = $today->startOfDay();
        $day = fn (CarbonImmutable $date) => $date->toDateString();

        if (preg_match('/\bultimos (\d{1,3}) dias\b/', $t, $match)) {
            return [$day($today->subDays((int) $match[1] - 1)), $day($today)];
        }

        if (str_contains($t, 'hoje')) {
            return [$day($today), $day($today)];
        }

        if (str_contains($t, 'ontem')) {
            return [$day($today->subDay()), $day($today->subDay())];
        }

        if (str_contains($t, 'semana passada')) {
            $start = $today->startOfWeek(CarbonInterface::MONDAY)->subWeek();

            return [$day($start), $day($start->addDays(6))];
        }

        if (preg_match('/\b(essa|esta|nessa|nesta) semana\b/', $t)) {
            return [$day($today->startOfWeek(CarbonInterface::MONDAY)), $day($today)];
        }

        $month = self::month($t, $today, pastOnly: true);

        if ($month !== null) {
            $range = DateRange::month(CarbonImmutable::createFromFormat('!Y-m', $month, $today->timezone));

            return [$day($range->from), $day($range->to)];
        }

        return [$day($today->startOfMonth()), $day($today)];
    }

    /**
     * O mês citado ("setembro", "mês passado") como "2026-09"; null quando não cita nenhum (o atual).
     * Em perguntas sobre gastos ($pastOnly), um mês que ainda não chegou é o do ano passado:
     * "quanto gastei em novembro?", dito em março, é sobre o novembro que já passou.
     */
    private static function month(string $t, CarbonImmutable $today, bool $pastOnly = false): ?string
    {
        if (preg_match('/\bmes passado\b/', $t)) {
            return $today->startOfMonth()->subMonth()->format('Y-m');
        }

        if (preg_match('/\b(proximo mes|mes que vem)\b/', $t)) {
            return $today->startOfMonth()->addMonth()->format('Y-m');
        }

        foreach (DateRange::MONTHS as $number => $name) {
            if (preg_match('/\b'.NameMatcher::normalize($name).'\b/', $t)) {
                $year = $pastOnly && $number > $today->month ? $today->year - 1 : $today->year;

                return sprintf('%d-%02d', $year, $number);
            }
        }

        return null;
    }

    private static function call(string $name, array $args): ToolCall
    {
        return new ToolCall('fake-'.$name, $name, array_filter($args, fn ($value) => $value !== null));
    }
}
