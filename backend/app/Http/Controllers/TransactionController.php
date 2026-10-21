<?php

namespace App\Http\Controllers;

use App\Enums\CategoryKind;
use App\Http\Requests\Finance\StoreTransactionRequest;
use App\Http\Requests\Finance\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\RecurringIncomeService;
use App\Services\TransactionService;
use App\Support\Month;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** As consultas já saem limitadas à casa atual e sem os privados de outras pessoas. */
class TransactionController extends Controller
{
    private const RELATIONS = ['category', 'card', 'creator', 'installmentPlan', 'recurringIncome'];

    public function __construct(
        private readonly TransactionService $transactions,
        private readonly RecurringIncomeService $incomes,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            // Intervalo de datas (o Início usa from=to=hoje para mostrar só os lançamentos do dia).
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'kind' => ['nullable', Rule::enum(CategoryKind::class)],
            'category_id' => ['nullable', 'integer'],
            'card_id' => ['nullable', 'integer'],
            'member_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $transactions = Transaction::query()
            ->with(self::RELATIONS)
            ->when($filters['month'] ?? null, function (Builder $query, string $month) use ($request) {
                $start = Month::parse($month, $request->user()->timezone);
                $query->whereBetween('occurred_on', [$start->toDateString(), $start->endOfMonth()->toDateString()]);
            })
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->where('occurred_on', '>=', $date))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->where('occurred_on', '<=', $date))
            ->when($filters['kind'] ?? null, fn (Builder $query, string $kind) => $query->where('kind', $kind))
            ->when($filters['category_id'] ?? null, fn (Builder $query, int $id) => $query->where('category_id', $id))
            ->when($filters['card_id'] ?? null, fn (Builder $query, int $id) => $query->where('card_id', $id))
            ->when($filters['member_id'] ?? null, fn (Builder $query, int $id) => $query->where('created_by', $id))
            ->when($filters['q'] ?? null, fn (Builder $query, string $text) => $query->whereLike('description', "%{$text}%"))
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->cursorPaginate($filters['per_page'] ?? 50)
            ->withQueryString();

        return TransactionResource::collection($transactions);
    }

    /** Com "Repetir todo mês", a receita nasce junto com a regra que a lança nos próximos meses. */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $data = Arr::except($request->validated(), 'repeat_monthly');

        $transaction = $request->boolean('repeat_monthly')
            ? $this->incomes->start($request->user(), $data)
            : $this->transactions->create($request->user(), $data);

        return (new TransactionResource($transaction->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Transaction $transaction): TransactionResource
    {
        return new TransactionResource($transaction->load(self::RELATIONS));
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): TransactionResource
    {
        $this->transactions->update($transaction, $request->validated());

        return new TransactionResource($transaction->load(self::RELATIONS));
    }

    public function destroy(Transaction $transaction): Response
    {
        Gate::authorize('delete', $transaction);

        $this->transactions->delete($transaction);

        return response()->noContent();
    }
}
