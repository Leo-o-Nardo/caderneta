<?php

namespace App\Http\Controllers;

use App\Http\Requests\Onboarding\UpdateOnboardingRequest;
use App\Services\OnboardingService;
use App\Support\HouseholdContext;
use Illuminate\Http\JsonResponse;

/**
 * Os primeiros passos na casa atual: as boas-vindas, o checklist e o ponto do tour. Até o "Só
 * leitura" usa (as boas-vindas e o tour), por isso fica fora do grupo que exige poder gravar.
 */
class OnboardingController extends Controller
{
    public function __construct(
        private readonly OnboardingService $onboarding,
        private readonly HouseholdContext $context,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json($this->state());
    }

    public function update(UpdateOnboardingRequest $request): JsonResponse
    {
        $this->onboarding->update($this->context->household(), $this->context->user(), $request->validated());

        // Devolve o estado inteiro: o app troca o cache sem precisar buscar de novo.
        return response()->json($this->state());
    }

    private function state(): array
    {
        return $this->onboarding->state($this->context->household(), $this->context->user(), $this->context->role());
    }
}
