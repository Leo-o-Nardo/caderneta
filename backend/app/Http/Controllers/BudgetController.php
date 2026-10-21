<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\UpsertBudgetRequest;
use App\Models\Category;
use App\Services\BudgetService;
use App\Support\BudgetUsage;
use App\Support\Month;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Os orçamentos são endereçados pela categoria: cada categoria tem no máximo um. */
class BudgetController extends Controller
{
    public function __construct(private readonly BudgetService $budgets) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = Month::parse($data['month'] ?? null, $request->user()->timezone);

        return response()->json(
            $this->budgets->forMonth($month)->map(fn (BudgetUsage $usage) => $usage->toArray())->all()
        );
    }

    /** Uma categoria, com ou sem orçamento: o limite, o gasto do mês e a média dos anteriores. */
    public function show(Request $request, Category $category): JsonResponse
    {
        abort_if($category->archived_at !== null, 404);

        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = Month::parse($data['month'] ?? null, $request->user()->timezone);

        return response()->json($this->budgets->detail($category, $month));
    }

    /** Responde com o uso do mês atual, para o app já mostrar a barra atualizada. */
    public function update(UpsertBudgetRequest $request, Category $category): JsonResponse
    {
        abort_if($category->archived_at !== null, 404);

        $budget = $this->budgets->set($category, $request->integer('limit_cents'));

        $usage = $this->budgets
            ->forMonth(Month::parse(null, $request->user()->timezone))
            ->first(fn (BudgetUsage $usage) => $usage->budget->is($budget));

        return response()->json($usage->toArray());
    }

    public function destroy(Category $category): Response
    {
        $this->budgets->remove($category);

        return response()->noContent();
    }
}
