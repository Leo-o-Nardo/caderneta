<?php

namespace App\Assistant\Llm\Fake;

use App\Assistant\AssistantContext;
use App\Assistant\Llm\LlmMessage;
use App\Assistant\NameMatcher;

/**
 * Os textos da IA simulada, escritos só com o que as ferramentas devolveram. É a mesma regra que a
 * IA real segue: nenhum número sai daqui que não tenha vindo de uma ferramenta.
 */
final class FakeReplies
{
    /** @param  list<LlmMessage>  $results  os resultados das ferramentas desta rodada */
    public static function fromTools(array $results): string
    {
        return implode(' ', array_map(
            fn (LlmMessage $result) => self::fromTool((string) $result->call?->name, $result->result),
            $results,
        ));
    }

    /** Quando nenhuma regra reconheceu a frase: cumprimento, agradecimento, ajuda ou "ainda não sei". */
    public static function smallTalk(string $text, AssistantContext $context): string
    {
        $t = NameMatcher::normalize($text);
        $name = strtok($context->userName, ' ') ?: $context->userName;

        return match (true) {
            (bool) preg_match('/^(oi|ola|bom dia|boa tarde|boa noite|e ai|opa)\b/', $t) => "Oi, {$name}! Pode perguntar coisas como \"quanto gastei com mercado esse mês?\" ou \"quais contas vencem esse mês?\".",
            (bool) preg_match('/\b(obrigad[oa]|valeu|brigad[oa])\b/', $t) => 'Por nada!',
            (bool) preg_match('/\b(o que (voce|vc) (faz|sabe)|ajuda|como funciona)\b/', $t) => 'Eu lanço, corrijo e apago gastos ("gastei 12 de café e 30 de uber"), marco contas como pagas, cadastro contas fixas, salário e orçamentos, cuido das listas e crio lembretes. Também respondo sobre os gastos, o resumo do mês, as faturas, as contas e os orçamentos.',
            default => 'Ainda não sei fazer isso. Dá para lançar e corrigir gastos, marcar contas como pagas, cadastrar contas fixas, salário e orçamentos, mexer nas listas, criar lembretes e perguntar sobre gastos, faturas, contas e orçamentos.',
        };
    }

    /** A frase pedia uma ação, mas quem fala é "só leitura" nesta casa (a ferramenta nem foi oferecida). */
    public static function notAllowed(): string
    {
        return 'Você está como só leitura nesta casa: dá para perguntar, mas não lançar nem mudar nada.';
    }

    private static function fromTool(string $tool, array $result): string
    {
        // Os erros das ferramentas já vêm escritos para a pessoa ("não achei a categoria…").
        if (isset($result['error'])) {
            return $result['error'];
        }

        // As ações devolvem o resumo pronto. Pendente, a resposta termina perguntando.
        if (isset($result['status'])) {
            return match ($result['status']) {
                'awaiting_confirmation' => $result['summary'].(in_array($tool, ['delete_transaction', 'delete_list'], true) ? ' Posso apagar?' : ' Confere?'),
                'ambiguous' => $result['question'],
                default => $result['summary'],
            };
        }

        return match ($tool) {
            'query_transactions' => self::transactions($result),
            'get_month_overview' => self::overview($result),
            'get_card_invoice' => self::invoice($result),
            'list_bills' => self::bills($result),
            'get_budget_status' => self::budgets($result),
            'get_list' => self::list($result),
            'list_reminders' => self::reminders($result),
            default => 'Pronto.',
        };
    }

    private static function transactions(array $r): string
    {
        $period = self::capitalize($r['period']['label']);
        $income = $r['kind'] === 'income';
        $filter = (isset($r['category']) ? " com {$r['category']}" : '')
            .(isset($r['text']) ? " com \"{$r['text']}\"" : '')
            .(isset($r['card']) ? " no {$r['card']}" : '');

        if ($r['count'] === 0) {
            return sprintf('%s, não encontrei %s%s.', $period, $income ? 'receitas' : 'gastos', $filter);
        }

        $count = self::plural($r['count'], 'lançamento', 'lançamentos');

        if (isset($r['category']) || ! isset($r['groups'])) {
            return sprintf('%s, %s %s%s, em %s.', $period, $income ? 'entraram' : 'foram', $r['total'], $filter, $count);
        }

        $groups = $r['groups'];
        usort($groups, fn (array $a, array $b) => $b['total_cents'] <=> $a['total_cents']);
        $top = array_map(fn (array $group) => "{$group['label']} ({$group['total']})", array_slice($groups, 0, 3));

        return sprintf(
            '%s, %s %s%s em %s. %s: %s.',
            $period,
            $income ? 'entraram' : 'os gastos somam',
            $r['total'],
            $filter,
            $count,
            count($top) === 1 ? 'Tudo em' : 'Os maiores',
            self::join($top),
        );
    }

    private static function overview(array $r): string
    {
        $text = sprintf(
            'Em %s%s, entraram %s e saíram %s: saldo de %s.',
            $r['month'],
            $r['is_current_month'] ? ' até agora' : '',
            $r['income'],
            $r['expense'],
            $r['balance'],
        );

        if ($r['top_categories'] !== []) {
            $text .= ' Onde mais foi: '.self::join(array_map(fn (array $c) => "{$c['name']} ({$c['total']})", array_slice($r['top_categories'], 0, 3))).'.';
        }

        if ($r['budgets_over'] !== []) {
            $text .= ' Passou do orçamento: '.self::join($r['budgets_over']).'.';
        }

        if ($r['next_bills'] !== []) {
            $bill = $r['next_bills'][0];
            $text .= sprintf(' Próxima conta: %s, %s, %s %s.', $bill['name'], self::amount($bill['amount'], $bill['is_estimate']), $bill['is_overdue'] ? 'venceu' : 'vence', $bill['due_on']);
        }

        return $text;
    }

    private static function invoice(array $r): string
    {
        if ($r['count'] === 0) {
            return sprintf('A fatura do %s de %s ainda está zerada (fecha em %s, vence em %s).', $r['card'], $r['month'], $r['closes_on'], $r['due_on']);
        }

        $text = sprintf(
            'A fatura do %s de %s %s %s (%s em %s, vence em %s).',
            $r['card'],
            $r['month'],
            $r['is_closed'] ? 'fechou em' : 'está em',
            $r['total'],
            $r['is_closed'] ? 'fechou' : 'fecha',
            $r['closes_on'],
            $r['due_on'],
        );

        if ($r['installments'] !== 'R$ 0,00') {
            $text .= " Desse total, {$r['installments']} são parcelas.";
        }

        return $text;
    }

    private static function bills(array $r): string
    {
        if ($r['bills'] === []) {
            return 'Ainda não há contas fixas cadastradas. Dá para criar na Agenda.';
        }

        if ($r['open_count'] === 0) {
            return sprintf('Todas as contas de %s estão em dia.', $r['month']);
        }

        $open = array_filter($r['bills'], fn (array $bill) => in_array($bill['status'], ['atrasada', 'vence hoje', 'a vencer'], true));
        $items = array_map(fn (array $bill) => sprintf(
            '%s (%s, %s)',
            $bill['name'],
            self::amount($bill['amount'], $bill['is_estimate']),
            match ($bill['status']) {
                'atrasada' => "venceu {$bill['due_on']}",
                'vence hoje' => 'vence hoje',
                default => "vence {$bill['due_on']}",
            },
        ), $open);

        $text = sprintf(
            'Em %s, %s %s, %s%s no total: %s.',
            $r['month'],
            $r['open_count'] === 1 ? 'falta' : 'faltam',
            self::plural($r['open_count'], 'conta', 'contas'),
            $r['open_total_is_estimate'] ? '~' : '',
            $r['open_total'],
            self::join(array_values($items)),
        );

        if ($r['paid_count'] > 0) {
            $text .= ' '.($r['paid_count'] === 1 ? '1 já foi paga.' : "{$r['paid_count']} já foram pagas.");
        }

        return $text;
    }

    private static function budgets(array $r): string
    {
        if ($r['budgets'] === []) {
            return $r['hint'];
        }

        $items = array_map(fn (array $budget) => sprintf(
            '%s %s de %s%s',
            $budget['category'],
            $budget['spent'],
            $budget['limit'],
            $budget['status'] === 'estourado' ? ' (estourado)' : " ({$budget['percent']}%)",
        ), array_slice($r['budgets'], 0, 5));

        return sprintf('Em %s: %s.', $r['month'], self::join($items));
    }

    private static function list(array $r): string
    {
        if ($r['open_count'] === 0) {
            return $r['checked_count'] > 0 ? "Tudo da lista {$r['list']} já foi pego." : "A lista {$r['list']} está vazia.";
        }

        $names = array_map(
            fn (array $item) => isset($item['quantity']) ? "{$item['text']} ({$item['quantity']})" : $item['text'],
            array_slice($r['items'], 0, 8),
        );

        if ($r['open_count'] > 8) {
            $names[] = 'mais '.($r['open_count'] - 8);
        }

        $text = sprintf(
            'Na lista %s %s %s: %s.',
            $r['list'],
            $r['open_count'] === 1 ? 'falta' : 'faltam',
            self::plural($r['open_count'], 'item', 'itens'),
            self::join($names),
        );

        if (isset($r['open_total'])) {
            $text .= " Somando os preços, dá {$r['open_total']}.";
        }

        if ($r['checked_count'] > 0) {
            $text .= ' '.($r['checked_count'] === 1 ? '1 já foi pego.' : "{$r['checked_count']} já foram pegos.");
        }

        return $text;
    }

    private static function reminders(array $r): string
    {
        if ($r['reminders'] === []) {
            return 'Você não tem lembretes pendentes.';
        }

        $items = array_map(
            fn (array $reminder) => $reminder['text'].' em '.$reminder['when'].($reminder['is_overdue'] ? ' (atrasado)' : ''),
            array_slice($r['reminders'], 0, 5),
        );

        return 'Próximos lembretes: '.implode('; ', $items).'.';
    }

    /** "R$ 120,00", "~R$ 170,00" (estimativa) ou "valor a definir". */
    private static function amount(?string $amount, bool $isEstimate): string
    {
        return $amount === null ? 'valor a definir' : ($isEstimate ? '~' : '').$amount;
    }

    /** ["a", "b", "c"] → "a, b e c". */
    private static function join(array $items): string
    {
        if (count($items) <= 1) {
            return implode('', $items);
        }

        return implode(', ', array_slice($items, 0, -1)).' e '.end($items);
    }

    private static function plural(int $count, string $one, string $many): string
    {
        return $count.' '.($count === 1 ? $one : $many);
    }

    private static function capitalize(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }
}
