<?php

namespace App\Http\Controllers;

use App\Enums\HouseholdRole;
use App\Http\Requests\Households\UpdateMemberRoleRequest;
use App\Http\Resources\MemberResource;
use App\Services\HouseholdService;
use App\Support\HouseholdContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class HouseholdMemberController extends Controller
{
    public function __construct(private readonly HouseholdService $households) {}

    public function update(UpdateMemberRoleRequest $request, HouseholdContext $context, int $member): MemberResource
    {
        $household = $context->household();
        Gate::authorize('manageMembers', $household);

        // Busca só entre os membros desta casa: alguém de outra casa dá 404.
        $user = $household->members()->findOrFail($member);

        $this->households->changeRole($household, $user, $request->enum('role', HouseholdRole::class));

        return new MemberResource($household->members()->findOrFail($member));
    }

    public function destroy(Request $request, HouseholdContext $context, int $member): Response
    {
        $household = $context->household();
        Gate::authorize('manageMembers', $household);

        if ($member === $request->user()->id) {
            throw ValidationException::withMessages(['member' => __('households.remove_self')]);
        }

        $this->households->removeMember($household, $household->members()->findOrFail($member));

        return response()->noContent();
    }
}
