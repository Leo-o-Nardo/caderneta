<?php

namespace App\Http\Resources;

use App\Models\HouseholdInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HouseholdInvitation */
class InvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'url' => config('app.invite_url').'/'.$this->code,
            'role' => $this->role,
            'invited_by' => $this->inviter->name,
            'expires_at' => $this->expires_at,
            'household' => $this->whenLoaded('household', fn () => [
                'name' => $this->household->name,
                'members_count' => $this->household->members_count,
            ]),
        ];
    }
}
