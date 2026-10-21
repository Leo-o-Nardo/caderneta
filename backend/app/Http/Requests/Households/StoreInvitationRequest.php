<?php

namespace App\Http\Requests\Households;

use App\Enums\HouseholdRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Nunca "owner": um link vazado não pode entregar o controle total da casa.
            'role' => ['required', Rule::enum(HouseholdRole::class)->only([HouseholdRole::Member, HouseholdRole::Viewer])],
        ];
    }
}
