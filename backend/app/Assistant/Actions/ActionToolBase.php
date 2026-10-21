<?php

namespace App\Assistant\Actions;

use App\Assistant\NameMatcher;
use App\Assistant\Tools\Tool;
use App\Assistant\Tools\ToolContext;
use App\Assistant\Tools\ToolResult;
use App\Models\Card;
use App\Models\Category;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * A base das ferramentas que gravam: prepara, valida com as regras do app e entrega ao
 * ActionService, que guarda a ação pendente (ou executa na hora, nas imediatas).
 */
abstract class ActionToolBase extends Tool implements ActionTool
{
    protected const PAYMENT_LABELS = [
        'debit' => 'no débito',
        'pix' => 'no Pix',
        'cash' => 'em dinheiro',
        'credit_card' => 'no cartão',
    ];

    protected function run(array $args, ToolContext $context): ToolResult
    {
        $prepared = $this->prepare($args, $context);

        if ($prepared instanceof ToolResult) {
            return $prepared;
        }

        try {
            $payload = $this->validatePayload($prepared->payload, $context);
        } catch (ValidationException $error) {
            return ToolResult::error(implode(' ', Arr::flatten($error->errors())));
        }

        return app(ActionService::class)->propose($this, new PreparedAction($payload, $prepared->summary), $context);
    }

    public function undo(array $result, ToolContext $context): void
    {
        throw new LogicException("A ação {$this->name()} não pode ser desfeita.");
    }

    /**
     * O cartão de uma compra no crédito: o citado pelo nome; sem nome, o padrão de quem fala ou o
     * único da casa. Sem como decidir, devolve a pergunta para a IA fazer.
     *
     * @param  Collection<int, Card>  $cards
     */
    protected function resolveCard(Collection $cards, ?string $name, ToolContext $context): Card|ToolResult
    {
        if ($cards->isEmpty()) {
            return ToolResult::error('A casa ainda não tem cartões cadastrados. Dá para cadastrar na Agenda, em Cartões.');
        }

        if ($name !== null) {
            return NameMatcher::find($cards, $name, fn (Card $card) => $card->name)
                ?? ToolResult::error(sprintf('Não encontrei o cartão "%s". Os cartões são: %s.', $name, $cards->pluck('name')->join(', ')));
        }

        return $cards->first(fn (Card $card) => $card->is_default && $card->user_id === $context->user->id)
            ?? ($cards->count() === 1 ? $cards->first() : null)
            ?? ToolResult::error('Em qual cartão? Os cartões da casa são: '.$cards->pluck('name')->join(', ').'.');
    }

    /**
     * O cartão de quem fala quando ninguém disse qual: o padrão dela ou, sem padrão, o primeiro
     * dela. O cartão de outra pessoa nunca entra sem ser citado.
     *
     * @param  Collection<int, Card>  $cards
     */
    protected function ownCard(Collection $cards, ToolContext $context): ?Card
    {
        $own = $cards->where('user_id', $context->user->id);

        return $own->firstWhere('is_default', true) ?? $own->first();
    }

    /**
     * A categoria citada pelo nome. Sem nome (ou sem achar), a primeira das $fallbacks que a casa
     * tiver ("Contas da casa", "Outros") e, por fim, a última da lista: a pessoa troca depois se quiser.
     *
     * @param  Collection<int, Category>  $categories  já só as do tipo certo
     * @param  list<string>  $fallbacks
     */
    protected static function pickCategory(Collection $categories, ?string $name, array $fallbacks): ?Category
    {
        $named = $name !== null ? NameMatcher::find($categories, $name, fn (Category $category) => $category->name) : null;

        if ($named) {
            return $named;
        }

        foreach ($fallbacks as $fallback) {
            $found = $categories->first(fn (Category $category) => NameMatcher::normalize($category->name) === NameMatcher::normalize($fallback));

            if ($found) {
                return $found;
            }
        }

        return $categories->last();
    }

    /** O pedaço do card que mostra a categoria. */
    protected static function categoryCard(?Category $category): ?array
    {
        return $category ? ['id' => $category->id, 'name' => $category->name, 'icon' => $category->icon, 'color' => $category->color] : null;
    }

    /** "no débito", "no cartão Nubank". */
    protected static function paymentLabel(?string $type, ?string $cardName): string
    {
        if ($type === 'credit_card' && $cardName) {
            return "no cartão {$cardName}";
        }

        return self::PAYMENT_LABELS[$type] ?? '';
    }

    /** "hoje", "ontem" ou "em 05/10". */
    protected static function dayLabel(string $date, CarbonImmutable $today): string
    {
        $day = CarbonImmutable::parse($date, $today->timezone);

        return match (true) {
            $day->isSameDay($today) => 'hoje',
            $day->isSameDay($today->subDay()) => 'ontem',
            default => 'em '.$day->format('d/m'),
        };
    }

    /** Reais da IA (12.5) em centavos (1250), sem erro de arredondamento. */
    protected static function cents(float|int|string $reais): int
    {
        return (int) round(((float) $reais) * 100);
    }

    protected static function money(int $cents): string
    {
        return Money::format($cents);
    }

    /** "café" → "Café", sem mexer no resto ("IPVA" continua "IPVA"). */
    protected static function capitalize(string $text): string
    {
        $text = trim($text);

        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }

    /** ["a", "b", "c"] → "a, b e c". */
    protected static function join(array $items): string
    {
        $items = array_values($items);

        if (count($items) <= 1) {
            return implode('', $items);
        }

        return implode(', ', array_slice($items, 0, -1)).' e '.end($items);
    }
}
