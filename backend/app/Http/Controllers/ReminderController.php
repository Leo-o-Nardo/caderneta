<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reminders\StoreReminderRequest;
use App\Http\Requests\Reminders\UpdateReminderRequest;
use App\Http\Resources\ReminderResource;
use App\Models\Reminder;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** Os lembretes privados de outras pessoas não aparecem (nem pelo id), pelo filtro automático. */
class ReminderController extends Controller
{
    public function __construct(private readonly ReminderService $reminders) {}

    /** ?status=upcoming (padrão): os ativos, do mais próximo ao mais distante. ?status=done: os concluídos. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(['upcoming', 'done'])]]);

        $reminders = Reminder::query()
            ->with('creator')
            ->when(
                ($data['status'] ?? 'upcoming') === 'done',
                fn ($query) => $query->whereNotNull('done_at')->orderByDesc('done_at')->limit(50),
                fn ($query) => $query->whereNull('done_at')->orderBy('remind_at'),
            )
            ->get();

        return ReminderResource::collection($reminders);
    }

    public function store(StoreReminderRequest $request): JsonResponse
    {
        $reminder = $this->reminders->create($request->user(), $request->validated());

        return (new ReminderResource($reminder->load('creator')))->response()->setStatusCode(201);
    }

    public function update(UpdateReminderRequest $request, Reminder $reminder): ReminderResource
    {
        $this->reminders->update($reminder, $request->user(), $request->validated());

        return new ReminderResource($reminder->load('creator'));
    }

    public function destroy(Reminder $reminder): Response
    {
        $reminder->delete();

        return response()->noContent();
    }

    public function done(Request $request, Reminder $reminder): ReminderResource
    {
        $this->reminders->done($reminder, $request->user());

        return new ReminderResource($reminder->load('creator'));
    }

    /** Sem "until", adia 24 horas ("me lembra amanhã"). */
    public function snooze(Request $request, Reminder $reminder): ReminderResource
    {
        $data = $request->validate(['until' => ['nullable', 'date_format:Y-m-d H:i,Y-m-d']]);

        $this->reminders->snooze($reminder, $request->user(), $data['until'] ?? null);

        return new ReminderResource($reminder->load('creator'));
    }
}
