<?php

namespace App\Assistant\Llm\Fake;

use App\Assistant\AssistantContext;
use App\Assistant\NameMatcher;
use App\Assistant\Privacy\SecureDataVault;
use Carbon\CarbonImmutable;

/**
 * As peças das frases de ação da IA simulada: valores ("12", "45,90", "2.500"), datas ("ontem",
 * "dia 20"), forma de pagamento, parcelas, itens de lista e lembretes. Trabalha no texto original
 * (sem tirar acento nem maiúscula) para "Café" e "IPVA" chegarem como foram ditos.
 */
final class FakeParser
{
    // Um valor em reais: "12", "45,90", "2.500", "1.234,56", "R$ 30", "30 reais".
    private const AMOUNT = '/(?:r\$\s*)?(?<![\d\/])(\d{1,3}(?:\.\d{3})+|\d+)(?:,(\d{1,2}))?(?![\d\/x])(?:\s*(?:reais|real|conto|contos)\b)?/iu';

    // A categoria pelas palavras mais comuns, para "12 de café" cair em Alimentação.
    private const KEYWORDS = [
        'Mercado' => ['mercado', 'supermercado', 'feira', 'acougue', 'padaria', 'hortifruti'],
        'Alimentação' => ['cafe', 'almoco', 'jantar', 'lanche', 'restaurante', 'pizza', 'hamburguer', 'sorvete', 'comida', 'marmita'],
        'Delivery' => ['ifood', 'delivery', 'rappi', 'entrega'],
        'Transporte' => ['uber', '99', 'taxi', 'onibus', 'metro', 'gasolina', 'combustivel', 'estacionamento', 'pedagio'],
        'Saúde' => ['farmacia', 'remedio', 'consulta', 'medico', 'dentista', 'exame'],
        'Lazer' => ['cinema', 'show', 'bar', 'cerveja', 'passeio', 'viagem', 'ingresso'],
        'Compras' => ['tv', 'televisao', 'roupa', 'tenis', 'celular', 'presente', 'shopping', 'loja', 'fone'],
        'Assinaturas' => ['netflix', 'spotify', 'assinatura', 'streaming', 'prime'],
        'Contas da casa' => ['luz', 'agua', 'internet', 'gas', 'condominio', 'telefone'],
        'Moradia' => ['aluguel'],
        'Pets' => ['racao', 'veterinario', 'petshop', 'pet'],
        'Educação' => ['curso', 'livro', 'escola', 'faculdade'],
        'Salário' => ['salario'],
        'Renda extra' => ['freela', 'freelance', 'bico', 'venda'],
    ];

    private const ARTICLES = '/^(?:o|a|os|as|um|uma|uns|umas)\s+/iu';

    // "todo dia 10", "todos os meses no dia 5", "dia 15": o dia de uma conta ou receita fixa.
    private const MONTH_DAY = '/\b(?:tod[oa]s?\s+(?:o\s+|os\s+)?(?:m[êe]s(?:es)?\s+)?)?(?:n[oa]\s+)?dia\s+\d{1,2}\b/iu';

    /** @return list<float> os valores da frase, na ordem */
    public static function amounts(string $text): array
    {
        $text = self::withoutInstallments(self::withoutDays(self::hideMarkers(self::withThousands($text))));
        preg_match_all(self::AMOUNT, $text, $matches, PREG_SET_ORDER);

        return array_map(
            fn (array $match) => (float) (str_replace('.', '', $match[1]).'.'.($match[2] ?? '0')),
            $matches,
        );
    }

    /** "em 10x", "10 vezes"; null quando não é parcelado. */
    public static function installments(string $text): ?int
    {
        return preg_match('/\b(\d{1,2})\s*(?:x\b|vezes\b)/iu', $text, $match) ? (int) $match[1] : null;
    }

    /** "pix", "débito", "dinheiro", "crédito"/"cartão"; null quando não diz. */
    public static function payment(string $text): ?string
    {
        $t = NameMatcher::normalize($text);

        return match (true) {
            (bool) preg_match('/\bpix\b/', $t) => 'pix',
            (bool) preg_match('/\bdebito\b/', $t) => 'debit',
            (bool) preg_match('/\b(dinheiro|especie)\b/', $t) => 'cash',
            (bool) preg_match('/\b(credito|cartao)\b/', $t) => 'credit_card',
            default => null,
        };
    }

    /**
     * A data dita: "hoje", "ontem", "anteontem", "amanhã", "dia 20", "20/10". Em gastos ($future
     * falso), "dia 20" ainda por vir é o do mês passado; em lembretes, o próximo dia 20.
     */
    public static function date(string $text, CarbonImmutable $today, bool $future = false): ?string
    {
        $t = NameMatcher::normalize($text);
        $today = $today->startOfDay();

        if (preg_match('/\banteontem\b/', $t)) {
            return $today->subDays(2)->toDateString();
        }

        if (preg_match('/\bontem\b/', $t)) {
            return $today->subDay()->toDateString();
        }

        if (preg_match('/\bamanha\b/', $t)) {
            return $today->addDay()->toDateString();
        }

        if (preg_match('/\bhoje\b/', $t)) {
            return $today->toDateString();
        }

        if (preg_match('/\b(\d{1,2})\/(\d{1,2})\b/', $t, $match)) {
            $date = $today->setDate($today->year, (int) $match[2], (int) $match[1]);

            return ($future && $date->lt($today) ? $date->addYear() : $date)->toDateString();
        }

        if (preg_match('/\bdia (\d{1,2})\b/', $t, $match)) {
            $date = $today->setDay(min((int) $match[1], $today->daysInMonth));

            if ($future && $date->lt($today)) {
                $date = $today->startOfMonth()->addMonthNoOverflow()->setDay((int) $match[1]);
            } elseif (! $future && $date->gt($today)) {
                $date = $today->startOfMonth()->subMonth()->setDay(min((int) $match[1], $today->startOfMonth()->subMonth()->daysInMonth));
            }

            return $date->toDateString();
        }

        return null;
    }

    /**
     * Os lançamentos de "gastei 12 de café e 30 de uber no pix": um item por valor, com a
     * descrição que sobra em volta dele. Data, forma de pagamento e cartão valem para todos.
     *
     * @return list<array<string, mixed>>
     */
    public static function transactions(string $text, AssistantContext $context, string $kind): array
    {
        $cards = array_column($context->cards, 'name');
        $card = NameMatcher::mentioned($cards, $text);
        $date = self::date($text, $context->today);
        $payment = self::payment($text);
        $installments = self::installments($text);

        // Tira o verbo do começo e o que vale para todos ("no pix", "ontem", "no nubank", "em 10x").
        $body = preg_replace('/^\s*(?:eu\s+)?(?:gastei|gastamos|paguei|pagamos|comprei|compramos|recebi|recebemos|ganhei|ganhamos)\s+/iu', '', self::withThousands($text));
        $body = self::withoutInstallments(self::withoutDays($body));
        $body = preg_replace('/\b(?:n[oa]|em|com)\s+(?:pix|d[ée]bito|cr[ée]dito|cart[ãa]o|dinheiro)\b/iu', ' ', $body);

        foreach ($cards as $name) {
            $body = preg_replace('/\b(?:n[oa]\s+)?'.preg_quote($name, '/').'\b/iu', ' ', $body);
        }

        $items = [];

        foreach (preg_split('/,\s+|\s+e\s+/iu', $body) as $segment) {
            // O valor é procurado fora dos marcadores do cofre ("Pix para [TELEFONE_1]").
            if (! preg_match(self::AMOUNT, self::hideMarkers($segment), $match, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $amount = (float) (str_replace('.', '', $match[1][0]).'.'.(($match[2][0] ?? '') ?: '0'));
            $description = self::description(substr_replace($segment, ' ', $match[0][1], strlen($match[0][0])));

            if ($description === '') {
                continue;
            }

            $items[] = array_filter([
                'kind' => $kind,
                'amount' => $amount,
                'description' => $description,
                'category' => self::category($description, $context, $kind),
                'date' => $date,
                'payment' => $card ? 'credit_card' : $payment,
                'card' => $card,
                'installments' => $installments,
            ], fn ($value) => $value !== null);
        }

        return $items;
    }

    /** Os itens de "coloca leite, café e pão na lista de mercado". */
    public static function listItems(string $text): array
    {
        $body = preg_replace('/^\s*(?:me\s+)?(?:coloca|coloque|adiciona|adicione|bota|bote|p[õo]e|ponha|inclui|inclua|anota|anote)\s+/iu', '', $text);
        $body = preg_replace('/\s+(?:n[ao]s?|à|a|pra|para)\s+(?:minha\s+)?listas?\b.*$/iu', '', $body);

        return self::splitItems($body);
    }

    /** Os itens de "peguei o leite e o pão", "marca o café como comprado". */
    public static function checkedItems(string $text): array
    {
        $body = preg_replace('/^\s*(?:j[áa]\s+)?(?:peguei|pegamos|marca|marque|risca|risque|comprei|compramos)\s+/iu', '', $text);
        $body = preg_replace('/\s+(?:como\s+)?(?:comprad[oa]s?|peg[oa]s?|feit[oa]s?)\b.*$/iu', '', $body);
        $body = preg_replace('/\s+(?:d[ao]|n[ao])\s+listas?\b.*$/iu', '', $body);

        return self::splitItems($body);
    }

    /**
     * "me lembra de pagar o IPVA dia 20 às 10h, todo mês" → o texto, quando, e a repetição. Sem dia,
     * amanhã; só com horário, hoje se ainda não passou.
     *
     * @return array<string, mixed>
     */
    public static function reminder(string $text, CarbonImmutable $today): array
    {
        // Tudo até o pedido ("me lembra de", "lembra-me de", "cria um lembrete para") sai.
        $body = preg_replace('/^.*?\b(?:me\s+lembr[ae]|lembr[ae]-?me|lembr[ae]|cri[ae]r?\s+um\s+lembrete)\s+(?:de\s+|para\s+|pra\s+|que\s+)?/iu', '', $text);
        $t = NameMatcher::normalize($text);

        [$recurrence, $every] = match (true) {
            (bool) preg_match('/\ba cada (\d+) dias?\b/', $t, $m) => ['daily', (int) $m[1]],
            (bool) preg_match('/\ba cada (\d+) semanas?\b/', $t, $m) => ['weekly', (int) $m[1]],
            (bool) preg_match('/\ba cada (\d+) mes(es)?\b/', $t, $m) => ['monthly', (int) $m[1]],
            (bool) preg_match('/\ba cada (\d+) anos?\b/', $t, $m) => ['yearly', (int) $m[1]],
            (bool) preg_match('/\b(todo dia \d|todo mes|todos os meses|mensalmente)\b/', $t) => ['monthly', 1],
            (bool) preg_match('/\b(todo dia|todos os dias|diariamente)\b/', $t) => ['daily', 1],
            (bool) preg_match('/\b(toda semana|todas as semanas|semanalmente)\b/', $t) => ['weekly', 1],
            (bool) preg_match('/\b(todo ano|todos os anos|anualmente)\b/', $t) => ['yearly', 1],
            default => [null, null],
        };

        $time = preg_match('/\b(?:às|as)\s+(\d{1,2})(?:[:h](\d{2}))?\s*h?\b|\b(\d{1,2})h(\d{2})?\b/iu', $text, $m)
            ? sprintf('%02d:%02d', (int) ($m[1] ?: $m[3]), (int) (($m[2] ?? '') ?: ($m[4] ?? 0)))
            : null;

        $date = self::date($text, $today, future: true);

        if ($date === null) {
            // Sem dia: hoje, se o horário ainda vai chegar; senão, amanhã.
            $date = $time !== null && $today->setTimeFromTimeString($time)->isFuture()
                ? $today->toDateString()
                : $today->addDay()->toDateString();
        }

        // O texto é o que sobra sem a data, o horário e a repetição.
        $body = preg_replace([
            '/\b(?:no\s+|n[ao]\s+)?(?:dia\s+\d{1,2}|\d{1,2}\/\d{1,2}|amanh[ãa]|hoje|depois de amanh[ãa])\b/iu',
            '/\b(?:às|as)\s+\d{1,2}(?:[:h]\d{2})?\s*h?\b|\b\d{1,2}h(?:\d{2})?\b/iu',
            '/,?\s*\b(?:a cada \d+ \w+|tod[oa]s? (?:o |os |as )?(?:dia|dias|semana|semanas|m[êe]s|meses|ano|anos)(?: \d{1,2})?|diariamente|semanalmente|mensalmente|anualmente)\b/iu',
        ], ' ', $body);

        return array_filter([
            'text' => self::clean($body),
            'when' => $time !== null ? "{$date} {$time}" : $date,
            'recurrence' => $recurrence,
            'every' => $every !== null && $every > 1 ? $every : null,
        ], fn ($value) => $value !== null);
    }

    /** O que sobra de "apaga o uber de ontem": "uber". */
    public static function deletionSubject(string $text): string
    {
        $body = preg_replace('/^\s*(?:apaga|apague|exclui|exclua|deleta|delete|remove|remova|tira|tire)\s+/iu', '', $text);
        $body = self::withoutDays($body);
        $body = preg_replace([self::AMOUNT, '/\b(?:o\s+)?(?:lan[çc]amento|gasto)\s+(?:d[eo]\s+)?/iu'], ' ', $body);

        return mb_strtolower(self::clean($body));
    }

    /** "todo dia 10", "vence dia 15": o dia do mês; null quando a frase não diz. */
    public static function dayOfMonth(string $text): ?int
    {
        return preg_match('/\bdia (\d{1,2})\b/', NameMatcher::normalize($text), $match) ? (int) $match[1] : null;
    }

    /**
     * "cadastra a internet de 99,90 todo dia 10", "a luz vence dia 15 e o valor muda" → o nome, o
     * valor, o dia e se o valor varia ou é débito automático.
     *
     * @return array<string, mixed>
     */
    public static function recurringBill(string $text): array
    {
        $t = NameMatcher::normalize($text);

        $body = preg_replace([
            '/^\s*(?:me\s+)?(?:cadastr[ae]|registr[ae]|adicion[ae]|cri[ae]|anot[ae])\s+(?:uma\s+|a\s+|o\s+)?(?:nova\s+)?(?:conta\s+(?:fixa\s+)?(?:d[aeo]\s+)?)?/iu',
            '/,?\s+e\s+o\s+valor\s+(?:muda|varia).*$/iu',
            '/\b(?:que\s+)?(?:vence|cai)\b/iu',
            self::MONTH_DAY,
            '/\b(?:n[oa]\s+|em\s+|com\s+)?d[ée]bito\s+autom[áa]tico\b/iu',
            '/\b(?:de\s+)?(?:valor\s+)?vari[áa]vel\b/iu',
        ], ' ', self::withThousands($text));

        return array_filter([
            'name' => self::description(preg_replace(self::AMOUNT, ' ', $body)),
            'amount' => self::amounts($text)[0] ?? null,
            'amount_varies' => preg_match('/\b(valor (muda|varia)|variavel)\b/', $t) ? true : null,
            'due_day' => self::dayOfMonth($text),
            'autopay' => preg_match('/\bdebito automatico\b/', $t) ? true : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * "recebo 5 mil de salário todo dia 5", "meu salário de 5.000 cai todo dia 5" → a descrição, o
     * valor, o dia e a categoria (pelas palavras-chave, como nos lançamentos).
     *
     * @return array<string, mixed>
     */
    public static function recurringIncome(string $text, AssistantContext $context): array
    {
        $body = preg_replace([
            '/^\s*(?:eu\s+)?(?:cadastr[ae]|registr[ae]|adicion[ae]|recebo|recebemos|ganho|ganhamos)\s+(?:uma\s+|a\s+|o\s+)?(?:receita\s+(?:fixa\s+)?(?:d[aeo]\s+)?)?/iu',
            '/^\s*(?:meu|minha|nosso|nossa)\s+/iu',
            '/\b(?:que\s+)?(?:cai|caem|entra|entram)\b/iu',
            self::MONTH_DAY,
        ], ' ', self::withThousands($text));

        $description = self::description(preg_replace(self::AMOUNT, ' ', $body));

        return array_filter([
            'description' => $description,
            'amount' => self::amounts($text)[0] ?? null,
            'day_of_month' => self::dayOfMonth($text),
            'category' => $description !== '' ? self::category($description, $context, 'income') : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * "cria uma lista de churrasco com carne, carvão e gelo" → o nome, o tipo (desejos, tarefas ou
     * compras) e os itens.
     *
     * @return array<string, mixed>
     */
    public static function newList(string $text): array
    {
        $body = preg_replace('/^.*?\blistas?\s+(?:nova\s+)?(?:d[eoa]s?\s+|pr[ao]\s+|para\s+(?:[oa]\s+)?)?/iu', '', $text, 1);
        [$name, $items] = array_pad(preg_split('/\s+com\s+/iu', $body, 2), 2, '');
        $t = NameMatcher::normalize($text);

        $kind = match (true) {
            (bool) preg_match('/\b(desejos?|presentes?)\b/', $t) => 'wishlist',
            (bool) preg_match('/\b(tarefas?|afazeres|pendencias)\b/', $t) => 'todo',
            default => 'shopping',
        };

        return array_filter([
            'name' => self::description($name),
            'kind' => $kind,
            'items' => $items !== '' ? self::splitItems($items) : null,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    /** O nome em "apaga a lista de churrasco": "Churrasco". */
    public static function listName(string $text): string
    {
        return self::description(preg_replace('/^.*?\blistas?\s+(?:d[eoa]s?\s+)?/iu', '', $text, 1));
    }

    /**
     * "o café de ontem foi 15, não 12", "corrige o uber de ontem para 25", "muda a categoria do
     * uber para lazer", "o almoço foi no nubank". O que vem antes de "foi"/"para" acha o lançamento;
     * o que vem depois é o que muda. Sem nada que mude, devolve só a busca.
     *
     * @return array<string, mixed>
     */
    public static function transactionUpdate(string $text, AssistantContext $context): array
    {
        $parts = preg_split('/\s+(?:para|pra|foi|era)\s+/iu', self::withThousands($text), 2);

        if (count($parts) < 2) {
            return [];
        }

        [$subject, $change] = $parts;
        $renaming = (bool) preg_match('/\b(descricao|nome)\b/', NameMatcher::normalize($subject));

        $search = preg_replace([
            '/^\s*(?:corrig[ea]|corrija|mud[ae]|alter[ae]|troc[ae]|arrum[ae]|acert[ae])\s+/iu',
            '/\b(?:[oa]\s+)?(?:categoria|valor|data|forma\s+de\s+pagamento|pagamento|descri[çc][ãa]o|nome)\s+d[oae]s?\s+/iu',
        ], ' ', self::withoutDays($subject));

        $amounts = self::amounts($change);
        $card = NameMatcher::mentioned(array_column($context->cards, 'name'), $change);
        $categories = [...$context->categoryNames('expense'), ...$context->categoryNames('income')];

        $new = $renaming ? ['new_description' => self::description($change)] : [
            'new_amount' => $amounts[0] ?? null,
            'new_category' => NameMatcher::mentioned($categories, $change),
            'new_date' => self::date($change, $context->today),
            'new_card' => $card,
            'new_payment' => $card === null ? self::payment($change) : null,
        ];

        return array_filter([
            'description' => mb_strtolower(self::clean(preg_replace(self::AMOUNT, ' ', $search))),
            'date' => self::date($subject, $context->today),
            // "foi 15, não 12": o valor depois do "não" é o de antes, e ajuda a achar o lançamento.
            'amount' => preg_match('/\bn[ãa]o\b/iu', $change) ? ($amounts[1] ?? null) : null,
            ...$new,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /** A categoria citada pelo nome ou adivinhada pelas palavras-chave; null deixa a ferramenta usar "Outros". */
    private static function category(string $description, AssistantContext $context, string $kind): ?string
    {
        $names = $context->categoryNames($kind);
        $named = NameMatcher::mentioned($names, $description);

        if ($named !== null) {
            return $named;
        }

        $words = NameMatcher::normalize($description);

        foreach (self::KEYWORDS as $category => $keywords) {
            if (in_array($category, $names, true) && preg_match('/\b('.implode('|', $keywords).')\b/', $words)) {
                return $category;
            }
        }

        return null;
    }

    /** "de café" → "Café", "uma tv" → "TV": sem preposição, artigo e espaços sobrando. */
    private static function description(string $text): string
    {
        $text = preg_replace('/^\s*(?:d[eoa]s?|n[oa]s?|em|com|pra|para)\s+/iu', '', trim($text));
        $text = preg_replace('/\s+(?:d[eoa]s?|n[oa]s?|em|com)\s*$/iu', '', $text);

        $clean = self::clean($text);

        return match (true) {
            $clean === '' => '',
            // Siglas curtas: "tv" vira "TV".
            mb_strlen($clean) <= 2 => mb_strtoupper($clean),
            default => mb_strtoupper(mb_substr($clean, 0, 1)).mb_substr($clean, 1),
        };
    }

    /** "leite, o café e pão" → ["Leite", "Café", "Pão"]. */
    private static function splitItems(string $text): array
    {
        $items = [];

        foreach (preg_split('/,\s*|\s+e\s+/iu', trim($text)) as $piece) {
            $piece = self::clean($piece);

            if ($piece !== '') {
                $items[] = mb_strtoupper(mb_substr($piece, 0, 1)).mb_substr($piece, 1);
            }
        }

        return $items;
    }

    private static function withoutDays(string $text): string
    {
        return preg_replace('/\b(?:de\s+|d[ao]\s+)?(?:anteontem|ontem|hoje|amanh[ãa]|dia\s+\d{1,2}|\d{1,2}\/\d{1,2})\b/iu', ' ', $text);
    }

    /**
     * Os marcadores do cofre ("[CPF_1]") viram "#" do mesmo tamanho: o "1" deles não é um valor, e
     * as posições no texto continuam as mesmas.
     */
    private static function hideMarkers(string $text): string
    {
        return preg_replace_callback(SecureDataVault::MARKER, fn (array $match) => str_repeat('#', strlen($match[0])), $text);
    }

    /** "5 mil" → "5.000", "2,5 mil" → "2.500": o jeito de falar valores redondos. */
    private static function withThousands(string $text): string
    {
        return preg_replace_callback(
            '/\b(\d+)(?:,(\d{1,2}))?\s*mil\b/iu',
            fn (array $match) => number_format((int) round((float) ($match[1].'.'.($match[2] ?? '0')) * 1000), 0, ',', '.'),
            $text,
        );
    }

    private static function withoutInstallments(string $text): string
    {
        return preg_replace('/\b(?:em\s+)?\d{1,2}\s*(?:x|vezes)\b/iu', ' ', $text);
    }

    /** Tira artigo do começo, pontuação das pontas e espaços repetidos. */
    private static function clean(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text), " \t\n\r\0\x0B.,;!?");

        return trim(preg_replace(self::ARTICLES, '', $text));
    }
}
