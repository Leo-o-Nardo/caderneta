<?php

namespace App\Http\Controllers;

use App\Http\Requests\Lists\StoreListItemsRequest;
use App\Http\Requests\Lists\UpdateListItemRequest;
use App\Http\Resources\ListItemResource;
use App\Models\HouseholdList;
use App\Models\ListItem;
use App\Services\ListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ListItemController extends Controller
{
    public function __construct(private readonly ListService $lists) {}

    /** Em aberto primeiro, na ordem da lista; os já pegos vão para o fim, os mais recentes antes. */
    public function index(Request $request, HouseholdList $list): AnonymousResourceCollection
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(['open', 'checked', 'all'])]]);
        $status = $data['status'] ?? 'all';

        $items = $list->items()
            ->with('checker')
            ->when($status === 'open', fn ($query) => $query->whereNull('checked_at'))
            ->when($status === 'checked', fn ($query) => $query->whereNotNull('checked_at'))
            ->orderByRaw('checked_at is not null')
            ->orderBy('position')
            ->orderByDesc('checked_at')
            ->get();

        return ListItemResource::collection($items);
    }

    /** Responde com o que entrou ("added") e o que já estava em aberto na lista ("existing"). */
    public function store(StoreListItemsRequest $request, HouseholdList $list): JsonResponse
    {
        $result = $this->lists->addItems($list, $request->user(), $request->validated('items'));

        return response()->json([
            'added' => ListItemResource::collection($result['added']->each->load('checker')),
            'existing' => ListItemResource::collection($result['existing']->each->load('checker')),
        ], $result['added']->isNotEmpty() ? 201 : 200);
    }

    public function update(UpdateListItemRequest $request, ListItem $listItem): ListItemResource
    {
        $this->lists->updateItem($listItem, $request->user(), $request->validated());

        return new ListItemResource($listItem->load('checker'));
    }

    public function destroy(ListItem $listItem): Response
    {
        $listItem->delete();

        return response()->noContent();
    }
}
