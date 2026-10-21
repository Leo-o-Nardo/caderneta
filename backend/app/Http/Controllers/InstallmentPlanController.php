<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\StoreInstallmentPlanRequest;
use App\Http\Requests\Finance\UpdateInstallmentPlanRequest;
use App\Http\Resources\InstallmentPlanResource;
use App\Models\InstallmentPlan;
use App\Services\InstallmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class InstallmentPlanController extends Controller
{
    public function __construct(private readonly InstallmentService $installments) {}

    public function store(StoreInstallmentPlanRequest $request): JsonResponse
    {
        $plan = $this->installments->create($request->user(), $request->validated());

        return (new InstallmentPlanResource($this->withDetails($plan)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(InstallmentPlan $installmentPlan): InstallmentPlanResource
    {
        return new InstallmentPlanResource($this->withDetails($installmentPlan));
    }

    public function update(UpdateInstallmentPlanRequest $request, InstallmentPlan $installmentPlan): InstallmentPlanResource
    {
        $plan = $this->installments->update($installmentPlan, $request->validated());

        return new InstallmentPlanResource($this->withDetails($plan));
    }

    public function destroy(InstallmentPlan $installmentPlan): Response
    {
        Gate::authorize('delete', $installmentPlan);

        $this->installments->delete($installmentPlan);

        return response()->noContent();
    }

    private function withDetails(InstallmentPlan $plan): InstallmentPlan
    {
        return $plan->load([
            'category',
            'card',
            'creator',
            'transactions' => fn ($query) => $query->orderBy('installment_number')->with(['category', 'card', 'creator', 'installmentPlan']),
        ]);
    }
}
