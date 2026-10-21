<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\PayBillRequest;
use App\Http\Requests\Finance\StoreBillRequest;
use App\Http\Requests\Finance\UpdateBillRequest;
use App\Http\Resources\BillResource;
use App\Models\RecurringBill;
use App\Services\BillService;
use App\Support\BillOccurrence;
use App\Support\Month;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BillController extends Controller
{
    private const RELATIONS = ['category', 'payments.transaction'];

    public function __construct(private readonly BillService $bills) {}

    public function index(): AnonymousResourceCollection
    {
        $bills = RecurringBill::query()
            ->active()
            ->with(self::RELATIONS)
            ->orderBy('due_day')
            ->orderBy('name')
            ->get();

        return BillResource::collection($bills);
    }

    public function store(StoreBillRequest $request): JsonResponse
    {
        $bill = $this->bills->create($request->user(), $request->validated(), $this->today($request));

        return (new BillResource($bill->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    /** A conta com os últimos 6 meses (pagos, pulados, atrasados), para a tela de detalhe. */
    public function show(Request $request, RecurringBill $bill): JsonResponse
    {
        $bill->load(self::RELATIONS);

        return response()->json([
            ...(new BillResource($bill))->resolve($request),
            'history' => $this->bills->history($bill, $this->today($request))
                ->map(fn (BillOccurrence $occurrence) => $occurrence->toArray(withBill: false))
                ->all(),
        ]);
    }

    public function update(UpdateBillRequest $request, RecurringBill $bill): BillResource
    {
        $bill->update($request->validated());

        return new BillResource($bill->load(self::RELATIONS));
    }

    /** Arquiva: os meses já pagos continuam no histórico, com as despesas lançadas. */
    public function destroy(RecurringBill $bill): Response
    {
        $bill->forceFill(['archived_at' => now()])->save();

        return response()->noContent();
    }

    public function upcoming(Request $request): JsonResponse
    {
        $data = $request->validate(['days' => ['nullable', 'integer', 'between:1,90']]);

        return $this->occurrences($this->bills->upcoming($this->today($request), (int) ($data['days'] ?? 30)));
    }

    public function month(Request $request): JsonResponse
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $today = $this->today($request);

        return $this->occurrences(
            $this->bills->forMonth(Month::parse($data['month'] ?? null, $today->timezoneName), $today)
        );
    }

    public function pay(PayBillRequest $request, RecurringBill $bill): JsonResponse
    {
        $occurrence = $this->bills->pay($bill, $request->user(), $request->validated(), $this->today($request));

        return response()->json($occurrence->toArray());
    }

    public function skip(Request $request, RecurringBill $bill): JsonResponse
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $occurrence = $this->bills->skip($bill, $data['month'] ?? null, $this->today($request));

        return response()->json($occurrence->toArray());
    }

    public function undo(Request $request, RecurringBill $bill, string $month): Response
    {
        $this->bills->undo($bill, Month::parse($month, $request->user()->timezone));

        return response()->noContent();
    }

    private function occurrences(iterable $occurrences): JsonResponse
    {
        return response()->json(
            collect($occurrences)->map(fn (BillOccurrence $occurrence) => $occurrence->toArray())->all()
        );
    }

    /** "Agora" no fuso do usuário: decide o que já venceu e o que vence hoje. */
    private function today(Request $request): CarbonImmutable
    {
        return CarbonImmutable::now($request->user()->timezone);
    }
}
