<?php

namespace App\Http\Controllers;

use App\Enums\HouseholdRole;
use App\Http\Requests\Households\StoreInvitationRequest;
use App\Http\Resources\HouseholdResource;
use App\Http\Resources\InvitationResource;
use App\Services\InvitationService;
use App\Support\HouseholdContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class InvitationController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    public function index(HouseholdContext $context): AnonymousResourceCollection
    {
        $household = $context->household();
        Gate::authorize('manageMembers', $household);

        return InvitationResource::collection(
            $household->invitations()->pending()->with('inviter')->latest()->get()
        );
    }

    public function store(StoreInvitationRequest $request, HouseholdContext $context): JsonResponse
    {
        $household = $context->household();
        Gate::authorize('manageMembers', $household);

        $invitation = $this->invitations->create(
            $household,
            $request->user(),
            $request->enum('role', HouseholdRole::class),
        );

        return (new InvitationResource($invitation->load('inviter')))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(HouseholdContext $context, int $invitation): Response
    {
        $household = $context->household();
        Gate::authorize('manageMembers', $household);

        $this->invitations->revoke($household->invitations()->pending()->findOrFail($invitation));

        return response()->noContent();
    }

    /** O que a pessoa convidada vê antes de aceitar. */
    public function show(string $code): InvitationResource
    {
        $invitation = $this->invitations->find($code) ?? abort(404);

        return new InvitationResource($invitation->load([
            'inviter',
            'household' => fn ($query) => $query->withCount('members'),
        ]));
    }

    public function accept(Request $request, string $code): HouseholdResource
    {
        $household = $this->invitations->accept($code, $request->user());

        return new HouseholdResource($household->loadCount('members'));
    }
}
