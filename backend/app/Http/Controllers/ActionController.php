<?php

namespace App\Http\Controllers;

use App\Assistant\Actions\ActionService;
use App\Http\Requests\Assistant\ConfirmActionRequest;
use App\Models\PendingAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Os botões dos cards do assistente. Nada aqui passa pela IA: o ActionService confere quem pede,
 * o papel na casa e o status, e grava o que o card mostrou (com as edições).
 */
class ActionController extends Controller
{
    public function __construct(private readonly ActionService $actions) {}

    public function confirm(ConfirmActionRequest $request, PendingAction $action): JsonResponse
    {
        $card = $this->actions->confirm($action, $request->user(), $request->validated('payload'));

        return $this->respond($action, $card);
    }

    public function cancel(Request $request, PendingAction $action): JsonResponse
    {
        return $this->respond($action, $this->actions->cancel($action, $request->user()));
    }

    public function undo(Request $request, PendingAction $action): JsonResponse
    {
        return $this->respond($action, $this->actions->undo($action, $request->user()));
    }

    /** O card atualizado e a mensagem dele: o app troca o card no lugar, sem buscar a conversa de novo. */
    private function respond(PendingAction $action, array $card): JsonResponse
    {
        return response()->json(['message_id' => $action->message_id, 'card' => $card]);
    }
}
