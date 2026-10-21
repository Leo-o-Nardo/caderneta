<?php

namespace App\Assistant\Tools;

use App\Assistant\Actions\ActionToolBase;
use App\Assistant\Actions\PreparedAction;
use App\Assistant\Actions\RequestRules;
use App\Assistant\Actions\ResolvesLists;
use App\Assistant\NameMatcher;
use App\Http\Requests\Lists\StoreListItemsRequest;
use App\Models\HouseholdList;
use App\Models\ListItem;
use App\Services\ListService;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * "Coloca leite, café e pão na lista". Grava na hora (no mercado, ninguém quer confirmar cada
 * item) e o card tem "Desfazer". O que já está na lista não se repete.
 */
final class AddListItems extends ActionToolBase
{
    use ResolvesLists;

    public function __construct(private readonly ListService $lists) {}

    public function name(): string
    {
        return 'add_list_items';
    }

    public function description(): string
    {
        return 'Coloca itens numa lista da casa ("coloca leite e pão na lista"; "quero um fone novo" vai para a lista '
            .'de desejos). Grava na hora e o card tem "Desfazer". Itens que já estão na lista não se repetem.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'list' => ['type' => 'string', 'description' => 'Nome da lista. Sem ele, a lista padrão.'],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => ['type' => 'string', 'description' => 'O item, curto: "Leite", "Fone de ouvido".'],
                            'quantity' => ['type' => 'string', 'description' => 'Como a pessoa disse: "2", "1 kg".'],
                            'price' => ['type' => 'number', 'description' => 'Preço em reais (lista de desejos).'],
                            'note' => ['type' => 'string'],
                        ],
                        'required' => ['text'],
                    ],
                ],
            ],
            'required' => ['items'],
        ];
    }

    public function tier(): ToolTier
    {
        return ToolTier::Immediate;
    }

    public function status(): string
    {
        return 'Atualizando a lista…';
    }

    protected function rules(): array
    {
        return [
            'list' => ['nullable', 'string', 'max:60'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.text' => ['required', 'string', 'max:120'],
            'items.*.quantity' => ['nullable', 'string', 'max:20'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function prepare(array $args, ToolContext $context): PreparedAction|ToolResult
    {
        $list = $this->findList($args['list'] ?? null);

        if ($list instanceof ToolResult) {
            return $list;
        }

        $items = array_map(fn (array $item) => array_filter([
            'text' => self::capitalize($item['text']),
            'quantity' => $item['quantity'] ?? null,
            'price_cents' => isset($item['price']) ? self::cents($item['price']) : null,
            'note' => $item['note'] ?? null,
        ], fn ($value) => $value !== null), $args['items']);

        // Só para o resumo: o ListService é quem decide de verdade (e ignora repetidos).
        $open = $list->items()->whereNull('checked_at')->pluck('text')->map(fn (string $text) => NameMatcher::normalize($text))->all();
        $new = [];
        $already = [];

        foreach ($items as $item) {
            $key = NameMatcher::normalize($item['text']);

            if (in_array($key, $open, true)) {
                $already[$key] = $item['text'];
            } else {
                $new[$key] = $item['text'];
            }
        }

        $summary = $new
            ? sprintf('Coloquei %s na lista %s.', self::join($new), $list->name)
            : '';

        if ($already) {
            $summary = trim($summary.' '.sprintf('%s já %s%s.', self::join($already), count($already) === 1 ? 'estava' : 'estavam', $new ? '' : " na lista {$list->name}"));
        }

        return new PreparedAction(['list_id' => $list->id, 'items' => array_values($items)], $summary);
    }

    /** As regras do formulário de itens do app, mais a lista, que precisa ser uma que a pessoa vê. */
    public function validatePayload(array $payload, ToolContext $context): array
    {
        if (! HouseholdList::query()->whereKey($payload['list_id'] ?? null)->exists()) {
            throw ValidationException::withMessages(['list_id' => __('assistant.actions.gone')]);
        }

        return [
            'list_id' => (int) $payload['list_id'],
            ...RequestRules::validate(StoreListItemsRequest::class, ['items' => $payload['items'] ?? []]),
        ];
    }

    public function card(array $payload, ?array $result, ToolContext $context): array
    {
        return [
            'type' => 'list_update',
            'list' => self::listCard(HouseholdList::query()->find($payload['list_id'])),
            'added' => $result['added_texts'] ?? array_column($payload['items'], 'text'),
            'already' => $result['existing_texts'] ?? [],
            'checked' => [],
            'not_found' => [],
        ];
    }

    /**
     * Guarda o que é novo e o que foi reaberto (já tinha sido pego e voltou para a lista), com o
     * estado anterior, para o "Desfazer" deixar tudo como estava.
     */
    public function execute(array $payload, ToolContext $context): array
    {
        $list = HouseholdList::query()->findOrFail($payload['list_id']);
        $before = $list->items()->get()->keyBy('id');
        $result = $this->lists->addItems($list, $context->user, $payload['items']);

        $created = [];
        $reopened = [];

        foreach ($result['added'] as $item) {
            $previous = $before->get($item->id);

            if ($previous) {
                $reopened[] = ['id' => $item->id, ...Arr::only($previous->getAttributes(), ['quantity', 'note', 'price_cents', 'url', 'due_on', 'position', 'checked_at', 'checked_by'])];
            } else {
                $created[] = $item->id;
            }
        }

        return [
            'list_id' => $list->id,
            'created' => $created,
            'reopened' => $reopened,
            'added_texts' => $result['added']->pluck('text')->all(),
            'existing_texts' => $result['existing']->pluck('text')->all(),
        ];
    }

    public function undo(array $result, ToolContext $context): void
    {
        ListItem::query()->whereKey($result['created'] ?? [])->delete();

        foreach ($result['reopened'] ?? [] as $previous) {
            ListItem::query()->whereKey($previous['id'])->first()?->forceFill(Arr::except($previous, 'id'))->save();
        }
    }
}
