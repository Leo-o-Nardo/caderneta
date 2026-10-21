<?php

namespace App\Http\Resources;

use App\Models\Household;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Household */
class HouseholdResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->membership?->role ?? $user->roleIn($this->resource),
            'is_current' => $this->id === $user->current_household_id,
            'members_count' => $this->whenCounted('members'),
            'members' => MemberResource::collection($this->whenLoaded('members')),
        ];
    }
}
