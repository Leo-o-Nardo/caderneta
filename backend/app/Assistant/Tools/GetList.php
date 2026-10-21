<?php

namespace App\Assistant\Tools;

use App\Assistant\Actions\ResolvesLists;
use App\Enums\ListKind;
use App\Models\ListItem;
use App\Support\Money;

/** Os itens de uma lista. As listas privadas de outras pessoas nem aparecem (filtro de visibilidade). */
final class GetList extends Tool
{
    use ResolvesLists;

    public function name(): string
    {
        return 'get_list';
    }

    public function description(): string
    {
        return 'Os itens de uma lista da casa (mercado, desejos, coisas a fazer…): o que falta e quantos já foram '
            .'pegos. Na lista de desejos, também a soma dos preços. Sem nome, a lista padrão.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'list' => ['type' => 'string', 'description' => 'Nome da lista, como na lista de listas.'],
            ],
        ];
    }

    public function status(): string
    {
        return 'Abrindo a lista…';
    }

    protected function rules(): array
    {
        return [
            'list' => ['nullable', 'string', 'max:60'],
        ];
    }

    protected function run(array $args, ToolContext $context): ToolResult
    {
        $list = $this->findList($args['list'] ?? null);

        if ($list instanceof ToolResult) {
            return $list;
        }

        $items = $list->items()->orderBy('position')->orderBy('id')->get();
        $open = $items->whereNull('checked_at');

        $data = [
            'list' => $list->name,
            'kind' => $list->kind->value,
            'open_count' => $open->count(),
            'checked_count' => $items->count() - $open->count(),
            'items' => $open->take(30)->values()->map(fn (ListItem $item) => array_filter([
                'text' => $item->text,
                'quantity' => $item->quantity,
                'price' => $item->price_cents !== null ? Money::format($item->price_cents) : null,
                'due_on' => $item->due_on ? self::shortDate($item->due_on) : null,
            ]))->all(),
        ];

        if ($list->kind === ListKind::Wishlist) {
            $data['open_total'] = Money::format((int) $open->sum('price_cents'));
        }

        return new ToolResult($data);
    }
}
