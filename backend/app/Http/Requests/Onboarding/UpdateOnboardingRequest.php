<?php

namespace App\Http\Requests\Onboarding;

use App\Services\OnboardingService;
use App\Support\HouseholdContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Uma escolha nos primeiros passos por vez, como o app manda: {"skip": "cards"}, {"hidden": true},
 * {"tour_step": 4}. Só dá para ignorar os passos que o papel da pessoa vê e que aceitam ser ignorados.
 */
class UpdateOnboardingRequest extends FormRequest
{
    public function rules(): array
    {
        $skippable = app(OnboardingService::class)->skippableFor(app(HouseholdContext::class)->role());

        return [
            'welcome_seen' => ['sometimes', 'accepted'],
            'hidden' => ['sometimes', 'boolean'],
            'skip' => ['sometimes', 'string', Rule::in($skippable)],
            'unskip' => ['sometimes', 'string', Rule::in($skippable)],
            'categories_reviewed' => ['sometimes', 'accepted'],
            'tour_step' => ['sometimes', 'nullable', 'integer', 'between:0,30'],
            'tour_completed' => ['sometimes', 'accepted'],
        ];
    }
}
