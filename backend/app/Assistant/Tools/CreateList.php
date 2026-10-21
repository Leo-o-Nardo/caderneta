<?php

namespace App\Assistant\Tools;

use App\Assistant\Actions\ActionToolBase;
use App\Assistant\Actions\PreparedAction;
use App\Assistant\Actions\RequestRules;
use App\Assistant\Actions\ResolvesLists;
use App\Assistant\NameMatcher;
use App\Enums\ListKind;
use App\Http\Requests\Lists\StoreListItemsRequest;
use App\Http\Requests\Lists\StoreListRequest;
use App\Models\HouseholdList;
use App\Services\ListService;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * "Cria uma lista de churrasco com carne, carvão e gelo". Cria a lista (e os itens, se ditos) na
 * hora; o "Desfazer" apaga a lista com o que foi colocado nela.
 */
final class CreateList extends ActionToolBase
{
    use ResolvesLists;

    public function __construct(private readonly ListService $lists) {}

    public function name(): string
    {
        return 'create_list';
    }

    public function description(): string
    {
        return 'Cria uma lista nova ("cria uma lista de churrasco com carne, carvão e gelo"). kind: shopping (compras, '
            .'o padrão), wishlist (desejos), todo (tarefas) ou custom. Pode já levar itens. Grava na hora e o card tem "Desfazer".';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Curto: "Churrasco", "Presentes de Natal".'],
                'kind' => ['type' => 'string', 'enum' => ['shopping', 'wishlist', 'todo', 'custom']],
                'items' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Itens para já colocar na lista.'],
                'private' => ['type' => 'boolean', 'description' => 'Só quem pediu vê. Padrão: a casa toda vê.'],
            ],
            'required' => ['name'],
        ];
    }

    public function tier(): ToolTier
    {
        return ToolTier::Immediate;
    }

    public function status(): string
    {
        return 'Criando a lista…';
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:40'],
            'kind' => ['nullable', Rule::enum(ListKind::class)],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*' => ['required', 'string', 'max:120'],
            'private' => ['nullable', 'boolean'],
        ];
    }

    public function prepare(array $args, ToolContext $context): PreparedAction|ToolResult
    {
        $name = self::capitalize($args['name']);
        $existing = HouseholdList::query()->get()
            ->first(fn (HouseholdList $list) => NameMatcher::normalize($list->name) === NameMatcher::normalize($name));

        if ($existing) {
            return ToolResult::error("Já existe a lista {$existing->name}. Para colocar itens nela, é só pedir: \"coloca … na lista {$existing->name}\".");
        }

        $items = array_map(fn (string $text) => ['text' => self::capitalize($text)], $args['items'] ?? []);

        $payload = [
            'name' => $name,
            'kind' => $args['kind'] ?? ListKind::Shopping->value,
            'visibility' => ($args['private'] ?? false) ? 'private' : 'household',
            'items' => $items,
        ];

        return new PreparedAction($payload, $items !== []
            ? sprintf('Pronto: criei a lista %s com %s.', $name, self::join(array_column($items, 'text')))
            : sprintf('Pronto: criei a lista %s.', $name));
    }

    /** As regras de "Nova lista" e, se houver itens, as de colocar itens na lista. */
    public function validatePayload(array $payload, ToolContext $context): array
    {
        $list = RequestRules::validate(StoreListRequest::class, Arr::except($payload, 'items'));
        $items = $payload['items'] ?? [];

        return [
            ...$list,
            'items' => $items !== [] ? RequestRules::validate(StoreListItemsRequest::class, ['items' => $items])['items'] : [],
        ];
    }

    public function card(array $payload, ?array $result, ToolContext $context): array
    {
        return [
            'type' => 'list_created',
            'list' => ['id' => $result['list_id'] ?? null, 'name' => $payload['name'], 'kind' => $payload['kind']],
            'items' => array_column($payload['items'] ?? [], 'text'),
        ];
    }

    public function execute(array $payload, ToolContext $context): array
    {
        $list = $this->lists->create($context->user, Arr::except($payload, 'items'));

        if (($payload['items'] ?? []) !== []) {
            $this->lists->addItems($list, $context->user, $payload['items']);
        }

        return ['list_id' => $list->id];
    }

    /** A lista sai de vez, com os itens (ainda é nova: só tem o que veio junto ou foi posto em 2 minutos). */
    public function undo(array $result, ToolContext $context): void
    {
        HouseholdList::query()->whereKey($result['list_id'] ?? null)->first()?->delete();
    }
}
