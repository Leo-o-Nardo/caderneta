<?php

namespace App\Assistant\Tools;

use App\Assistant\Actions\ActionToolBase;
use App\Assistant\Actions\PreparedAction;
use App\Assistant\Actions\ResolvesLists;
use App\Assistant\NameMatcher;
use App\Models\HouseholdList;
use App\Models\ListItem;
use App\Services\ListService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** "Peguei o leite e o pão": marca os itens como pegos. Grava na hora, com "Desfazer". */
final class CheckListItems extends ActionToolBase
{
    use ResolvesLists;

    public function __construct(private readonly ListService $lists) {}

    public function name(): string
    {
        return 'check_list_items';
    }

    public function description(): string
    {
        return 'Marca itens de uma lista como pegos ou feitos ("peguei o leite e o pão", "risca o detergente"). '
            .'Grava na hora e o card tem "Desfazer". Sem nome de lista, a padrão.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'list' => ['type' => 'string', 'description' => 'Nome da lista. Sem ele, a lista padrão.'],
                'items' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Os itens, como a pessoa disse.'],
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
            'items.*' => ['string', 'max:120'],
        ];
    }

    public function prepare(array $args, ToolContext $context): PreparedAction|ToolResult
    {
        $list = $this->findList($args['list'] ?? null);

        if ($list instanceof ToolResult) {
            return $list;
        }

        $open = $list->items()->whereNull('checked_at')->orderBy('position')->get();
        $found = [];
        $notFound = [];

        foreach ($args['items'] as $text) {
            $item = NameMatcher::find($open, $text, fn (ListItem $candidate) => $candidate->text);

            if ($item) {
                $found[$item->id] = $item->text;
            } else {
                $notFound[] = self::capitalize($text);
            }
        }

        if ($found === []) {
            return ToolResult::error(sprintf('Não achei %s em aberto na lista %s.', self::join($notFound), $list->name));
        }

        $summary = sprintf('Marquei %s como %s na lista %s.', self::join($found), count($found) === 1 ? 'pego' : 'pegos', $list->name)
            .($notFound ? sprintf(' Não achei %s em aberto.', self::join($notFound)) : '');

        return new PreparedAction([
            'list_id' => $list->id,
            'item_ids' => array_keys($found),
            'texts' => array_values($found),
            'not_found' => $notFound,
        ], $summary);
    }

    public function validatePayload(array $payload, ToolContext $context): array
    {
        $valid = Validator::make($payload, [
            'list_id' => ['required', 'integer'],
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer'],
            'texts' => ['array'],
            'not_found' => ['array'],
        ])->validate();

        if (! HouseholdList::query()->whereKey($valid['list_id'])->exists()) {
            throw ValidationException::withMessages(['list_id' => __('assistant.actions.gone')]);
        }

        return $valid;
    }

    public function card(array $payload, ?array $result, ToolContext $context): array
    {
        return [
            'type' => 'list_update',
            'list' => self::listCard(HouseholdList::query()->find($payload['list_id'])),
            'added' => [],
            'already' => [],
            'checked' => $payload['texts'] ?? [],
            'not_found' => $payload['not_found'] ?? [],
        ];
    }

    /** Só os que ainda estão em aberto: o "Desfazer" não pode reabrir o que alguém já tinha pego antes. */
    public function execute(array $payload, ToolContext $context): array
    {
        $list = HouseholdList::query()->findOrFail($payload['list_id']);
        $ids = $list->items()->whereNull('checked_at')->whereKey($payload['item_ids'])->pluck('id')->all();

        $this->lists->check($list, $context->user, $ids);

        return ['item_ids' => $ids];
    }

    public function undo(array $result, ToolContext $context): void
    {
        ListItem::query()->whereKey($result['item_ids'] ?? [])->update(['checked_at' => null, 'checked_by' => null]);
    }
}
