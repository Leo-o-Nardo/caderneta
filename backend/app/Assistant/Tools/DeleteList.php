<?php

namespace App\Assistant\Tools;

use App\Assistant\Actions\ActionToolBase;
use App\Assistant\Actions\PreparedAction;
use App\Assistant\Actions\ResolvesLists;
use App\Models\HouseholdList;
use App\Services\ListService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * "Apaga a lista de churrasco". Mostra um card com o que some junto (os itens) antes de apagar,
 * como o "apaga" de lançamento. A lista padrão não se apaga: é nela que cai o "coloca na lista".
 */
final class DeleteList extends ActionToolBase
{
    use ResolvesLists;

    public function __construct(private readonly ListService $lists) {}

    public function name(): string
    {
        return 'delete_list';
    }

    public function description(): string
    {
        return 'Apaga uma lista inteira, com os itens ("apaga a lista de churrasco"). A lista padrão não pode ser apagada. '
            .'Mostra um card com o impacto antes de apagar.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'list' => ['type' => 'string', 'description' => 'Nome da lista, como na lista de listas.'],
            ],
            'required' => ['list'],
        ];
    }

    public function tier(): ToolTier
    {
        return ToolTier::Explicit;
    }

    public function status(): string
    {
        return 'Procurando a lista…';
    }

    protected function rules(): array
    {
        return [
            'list' => ['required', 'string', 'max:60'],
        ];
    }

    public function prepare(array $args, ToolContext $context): PreparedAction|ToolResult
    {
        $list = $this->findList($args['list']);

        if ($list instanceof ToolResult) {
            return $list;
        }

        if ($list->is_default) {
            return ToolResult::error(sprintf(
                'A lista %s é a padrão (é nela que cai o "coloca na lista"). Escolha outra lista padrão na tela de listas antes de apagar.',
                $list->name,
            ));
        }

        $payload = [
            'list_id' => $list->id,
            // O card continua mostrando o que foi apagado depois que a lista some.
            'snapshot' => [
                'name' => $list->name,
                'kind' => $list->kind->value,
                'items_count' => $list->items()->count(),
                'open_count' => $list->items()->whereNull('checked_at')->count(),
            ],
        ];

        return new PreparedAction($payload, sprintf('Vou apagar a lista %s. %s', $list->name, self::impact($payload['snapshot'])));
    }

    /** Apagar não tem o que editar: vale a lista que o card mostrou. */
    public function validatePayload(array $payload, ToolContext $context): array
    {
        Validator::make($payload, ['list_id' => ['required', 'integer']])->validate();

        return $payload;
    }

    public function card(array $payload, ?array $result, ToolContext $context): array
    {
        $snapshot = $payload['snapshot'];

        return [
            'type' => 'list_delete',
            'list' => ['id' => $payload['list_id'], 'name' => $snapshot['name'], 'kind' => $snapshot['kind']],
            'impact' => self::impact($snapshot),
        ];
    }

    public function execute(array $payload, ToolContext $context): array
    {
        $list = HouseholdList::query()->find($payload['list_id'])
            ?? throw ValidationException::withMessages(['action' => __('assistant.actions.gone')]);

        // O ListService recusa a lista padrão (ela pode ter virado padrão depois do card).
        $this->lists->delete($list);

        return ['deleted_id' => $payload['list_id']];
    }

    /** "5 itens somem junto (3 em aberto)." ou "A lista está vazia." */
    private static function impact(array $snapshot): string
    {
        $total = $snapshot['items_count'];
        $open = $snapshot['open_count'];

        if ($total === 0) {
            return 'A lista está vazia.';
        }

        return sprintf(
            '%s junto%s.',
            $total === 1 ? '1 item some' : "{$total} itens somem",
            $open > 0 ? " ({$open} em aberto)" : '',
        );
    }
}
