<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\StoreRecurringIncomeRequest;
use App\Http\Requests\Finance\UpdateRecurringIncomeRequest;
use App\Http\Resources\RecurringIncomeResource;
use App\Models\RecurringIncome;
use App\Services\RecurringIncomeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * As receitas fixas da casa (Agenda › Contas › "Receitas fixas"). Elas nascem por aqui (o
 * "Adicionar" da Agenda) ou pelo "Repetir todo mês" ao lançar uma receita (TransactionController@store).
 */
class RecurringIncomeController extends Controller
{
    private const RELATIONS = ['category', 'creator'];

    public function __construct(private readonly RecurringIncomeService $incomes) {}

    public function index(): AnonymousResourceCollection
    {
        $incomes = RecurringIncome::query()
            ->active()
            ->with(self::RELATIONS)
            ->orderBy('day_of_month')
            ->orderBy('description')
            ->get();

        return RecurringIncomeResource::collection($incomes);
    }

    /** Começa no próximo dia que cair; se o dia é hoje, a receita de hoje já entra. */
    public function store(StoreRecurringIncomeRequest $request): JsonResponse
    {
        $income = $this->incomes->create(
            $request->user(),
            $request->validated(),
            CarbonImmutable::now($request->user()->timezone),
        );

        return (new RecurringIncomeResource($income->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    public function show(RecurringIncome $recurringIncome): RecurringIncomeResource
    {
        return new RecurringIncomeResource($recurringIncome->load(self::RELATIONS));
    }

    public function update(UpdateRecurringIncomeRequest $request, RecurringIncome $recurringIncome): RecurringIncomeResource
    {
        $recurringIncome->update($request->validated());

        return new RecurringIncomeResource($recurringIncome->load(self::RELATIONS));
    }

    /** "Parar de repetir": os lançamentos dos meses que já passaram continuam. */
    public function destroy(RecurringIncome $recurringIncome): Response
    {
        Gate::authorize('delete', $recurringIncome);

        $this->incomes->stop($recurringIncome);

        return response()->noContent();
    }
}
