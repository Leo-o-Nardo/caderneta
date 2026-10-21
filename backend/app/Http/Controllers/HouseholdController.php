<?php

namespace App\Http\Controllers;

use App\Http\Requests\Households\StoreHouseholdRequest;
use App\Http\Requests\Households\SwitchHouseholdRequest;
use App\Http\Requests\Households\UpdateHouseholdRequest;
use App\Http\Resources\HouseholdResource;
use App\Services\HouseholdService;
use App\Support\HouseholdContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class HouseholdController extends Controller
{
    public function __construct(private readonly HouseholdService $households) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $households = $request->user()->households()
            ->withCount('members')
            ->orderBy('households.name')
            ->get();

        return HouseholdResource::collection($households);
    }

    public function store(StoreHouseholdRequest $request): JsonResponse
    {
        $household = $this->households->createFor($request->user(), $request->validated('name'));

        return (new HouseholdResource($household->loadCount('members')))
            ->response()
            ->setStatusCode(201);
    }

    public function switchCurrent(SwitchHouseholdRequest $request): HouseholdResource
    {
        // Buscar só entre as casas do próprio usuário faz a casa de outra pessoa dar 404.
        $household = $request->user()->households()->findOrFail($request->validated('household_id'));

        $this->households->switchTo($request->user(), $household);

        return new HouseholdResource($household->loadCount('members'));
    }

    public function show(HouseholdContext $context): HouseholdResource
    {
        $household = $context->household()
            ->load(['members' => fn ($query) => $query->orderBy('household_user.created_at')->orderBy('household_user.id')])
            ->loadCount('members');

        return new HouseholdResource($household);
    }

    public function update(UpdateHouseholdRequest $request, HouseholdContext $context): HouseholdResource
    {
        $household = $context->household();
        Gate::authorize('update', $household);

        $household->update($request->validated());

        return new HouseholdResource($household->loadCount('members'));
    }

    public function destroy(HouseholdContext $context): Response
    {
        $household = $context->household();
        Gate::authorize('delete', $household);

        $this->households->delete($household);

        return response()->noContent();
    }

    public function leave(Request $request, HouseholdContext $context): Response
    {
        $this->households->leave($request->user(), $context->household());

        return response()->noContent();
    }
}
