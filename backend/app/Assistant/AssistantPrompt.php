<?php

namespace App\Assistant;

use App\Support\DateRange;

/**
 * As instruções fixas do assistente mais o contexto do dia. É o texto que a IA real (Gemini) lê
 * antes da conversa; a simulada usa o contexto estruturado direto.
 */
final class AssistantPrompt
{
    private const WEEKDAYS = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];

    private const RULES = <<<'TEXT'
        Você é o assistente financeiro de uma casa, dentro de um app de celular. Responda em português do Brasil.

        Regras:
        - Nunca invente números. Todo valor, data ou total vem de uma ferramenta; copie os valores já formatados ("R$ 1.234,56").
        - Sempre diga o período considerado ("de 1º a 9 de outubro", "em setembro").
        - Se faltar um dado para consultar (qual cartão, qual mês), pergunte antes.
        - Use os nomes de categorias, cartões e listas exatamente como aparecem abaixo.
        - Não recomende investimentos nem produtos financeiros.
        - Responda curto, no máximo 3 frases, sem tabelas nem markdown: a tela é de celular e os cards já mostram os detalhes.
        - Dados sensíveis chegam como marcadores ([CPF_1], [TELEFONE_1], [CARTAO_1], [EMAIL_1], [CONTA_1]…). Use-os exatamente como vieram, inclusive nos argumentos das ferramentas, sem criar novos nem tentar adivinhar o conteúdo.

        Ações:
        - Para lançar, corrigir ou apagar lançamentos, pagar conta, mexer em listas, criar lembretes, cadastrar contas fixas, receitas fixas e orçamentos, use as ferramentas. Valores em reais com ponto decimal (12.5).
        - Lançamentos, correções, pagamentos e exclusões (de lançamento ou de lista) viram um card para a pessoa conferir. Depois de chamar a ferramenta, conte o resumo e pergunte "Confere?". Nunca diga que já anotou, corrigiu ou apagou: só a pessoa confirma.
        - Itens de lista, lembretes, listas novas, contas fixas, receitas fixas e orçamentos são gravados na hora; o card tem "Desfazer". Conte o que foi feito.
        - Se a ferramenta devolver candidatos ("qual deles?"), pergunte à pessoa e depois chame de novo com o id escolhido.
        - Nas mensagens anteriores, [ação: …] diz o que a pessoa fez com cada card, e [aviso do app] marca os avisos que o próprio app mandou (conta vencendo, lembrete): eles explicam a que a pessoa está respondendo.
        TEXT;

    public static function system(AssistantContext $context): string
    {
        $today = $context->today;
        $date = sprintf(
            '%s, %d de %s de %d',
            self::WEEKDAYS[$today->dayOfWeek],
            $today->day,
            DateRange::MONTHS[$today->month],
            $today->year,
        );

        $lines = [
            self::RULES,
            '',
            "Hoje é {$date}, {$today->format('H:i')} (fuso {$today->timezoneName}).",
            "Você fala com {$context->userName}, da casa \"{$context->householdName}\".",
            'Categorias de despesa: '.implode(', ', $context->categoryNames('expense')).'.',
            'Categorias de receita: '.implode(', ', $context->categoryNames('income')).'.',
            'Cartões: '.(self::cards($context) ?: 'nenhum cadastrado').'.',
            'Listas: '.(self::lists($context) ?: 'nenhuma').'.',
            'Contas fixas: '.(self::bills($context) ?: 'nenhuma').'.',
            'Receitas fixas: '.(self::incomes($context) ?: 'nenhuma').'.',
            'Orçamentos por mês: '.(self::budgets($context) ?: 'nenhum').'.',
        ];

        return implode("\n", $lines);
    }

    private static function cards(AssistantContext $context): string
    {
        return implode('; ', array_map(
            fn (array $card) => $card['name'].' (de '.$card['owner'].($card['is_mine'] && $card['is_default'] ? ', o padrão de quem fala' : '').')',
            $context->cards,
        ));
    }

    private static function bills(AssistantContext $context): string
    {
        return implode('; ', array_map(
            fn (array $bill) => $bill['name'].($bill['amount_varies'] ? ' (valor variável)' : ''),
            $context->bills,
        ));
    }

    private static function incomes(AssistantContext $context): string
    {
        return implode('; ', array_map(
            fn (array $income) => "{$income['description']} (todo dia {$income['day_of_month']})",
            $context->incomes,
        ));
    }

    private static function budgets(AssistantContext $context): string
    {
        return implode('; ', array_map(
            fn (array $budget) => "{$budget['category']} {$budget['limit']}",
            $context->budgets,
        ));
    }

    private static function lists(AssistantContext $context): string
    {
        return implode('; ', array_map(
            fn (array $list) => $list['name'].' ('.$list['kind'].($list['is_default'] ? ', padrão' : '').')',
            $context->lists,
        ));
    }
}
