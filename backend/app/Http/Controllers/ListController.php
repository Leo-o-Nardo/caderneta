<?php

namespace App\Http\Controllers;

use App\Http\Requests\Lists\ReorderListRequest;
use App\Http\Requests\Lists\StoreListRequest;
use App\Http\Requests\Lists\UpdateListRequest;
use App\Http\Resources\ListResource;
use App\Models\HouseholdList;
use App\Services\ListService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** As listas privadas de outras pessoas não aparecem (nem pelo id), pelo filtro automático. */
class ListController extends Controller
{
    public function __construct(private readonly ListService $lists) {}

    public function index(): AnonymousResourceCollection
    {
        return ListResource::collection(
            $this->withCounts(HouseholdList::query())->orderBy('position')->orderBy('id')->get()
        );
    }

    public function store(StoreListRequest $request): JsonResponse
    {
        $list = $this->lists->create($request->user(), $request->validated());

        return (new ListResource($this->fresh($list)))->response()->setStatusCode(201);
    }

    public function update(UpdateListRequest $request, HouseholdList $list): ListResource
    {
        $this->lists->update($list, $request->validated());

        return new ListResource($this->fresh($list));
    }

    /** Apaga a lista com todos os itens. A lista padrão não pode ser apagada. */
    public function destroy(HouseholdList $list): Response
    {
        $this->lists->delete($list);

        return response()->noContent();
    }

    /** "Marcar tudo como comprado", ou só os itens informados em item_ids. */
    public function check(Request $request, HouseholdList $list): JsonResponse
    {
        $data = $request->validate([
            'item_ids' => ['nullable', 'array'],
            'item_ids.*' => ['integer'],
        ]);

        $checked = $this->lists->check($list, $request->user(), $data['item_ids'] ?? null);

        return response()->json(['checked' => $checked]);
    }

    /** Apaga os itens já comprados. */
    public function clearChecked(HouseholdList $list): JsonResponse
    {
        return response()->json(['deleted' => $this->lists->clearChecked($list)]);
    }

    public function reorder(ReorderListRequest $request, HouseholdList $list): Response
    {
        $this->lists->reorder($list, $request->validated('ids'));

        return response()->noContent();
    }

    private function fresh(HouseholdList $list): HouseholdList
    {
        return $this->withCounts(HouseholdList::query())->findOrFail($list->id);
    }

    /** Total de itens, itens em aberto e a soma dos preços em aberto (lista de desejos). */
    private function withCounts(Builder $query): Builder
    {
        return $query
            ->withCount(['items', 'items as open_items_count' => fn ($items) => $items->whereNull('checked_at')])
            ->withSum(['items as open_items_sum_price_cents' => fn ($items) => $items->whereNull('checked_at')], 'price_cents');
    }
}
