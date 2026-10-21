<?php

namespace App\Http\Controllers;

use App\Alerts\AlertActions;
use App\Enums\MessageRole;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Support\HouseholdContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Os avisos no Chat: o número vermelho da aba (não vistos), o "visto" e os botões dos cards. */
class AlertController extends Controller
{
    /** Quantos avisos chegaram desde a última vez que a pessoa abriu o Chat, na casa atual. */
    public function unread(Request $request, HouseholdContext $context): JsonResponse
    {
        $conversation = Conversation::activeFor($request->user(), $context->household());

        $count = $conversation === null ? 0 : $conversation->messages()
            ->where('role', MessageRole::Event)
            ->when($conversation->alerts_seen_at, fn ($query, $seen) => $query->where('created_at', '>', $seen))
            ->count();

        return response()->json(['count' => $count]);
    }

    /** A pessoa abriu o Chat: o número da aba zera. */
    public function seen(Request $request, HouseholdContext $context): Response
    {
        Conversation::activeFor($request->user(), $context->household())?->forceFill(['alerts_seen_at' => now()])->save();

        return response()->noContent();
    }

    /**
     * Um botão do card de aviso. O aviso precisa ser de quem toca, e a ação roda na casa do aviso
     * (mesmo que a pessoa tenha trocado de casa depois). Quem é só leitura não muda nada.
     */
    public function act(Request $request, Message $message, string $action, AlertActions $actions): JsonResponse
    {
        $conversation = $message->conversation;
        $user = $request->user();

        abort_if($message->role !== MessageRole::Event || $conversation->user_id !== $user->id, 404);

        $role = $user->roleIn($conversation->household);

        abort_if($role === null, 404);
        abort_unless($role->canWrite(), 403);

        app(HouseholdContext::class)->set($conversation->household, $role, $user);

        $actions->run($message, $action, $user);

        return response()->json(['message' => new MessageResource($message->refresh())]);
    }
}
