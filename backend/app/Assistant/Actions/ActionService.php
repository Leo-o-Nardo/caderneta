<?php

namespace App\Assistant\Actions;

use App\Assistant\Tools\ToolContext;
use App\Assistant\Tools\ToolRegistry;
use App\Assistant\Tools\ToolResult;
use App\Assistant\Tools\ToolTier;
use App\Enums\ActionStatus;
use App\Events\Assistant\ActionUpdated;
use App\Models\PendingAction;
use App\Models\User;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

/**
 * O ciclo das ações do assistente. A IA só propõe; confirmar, cancelar e desfazer chegam pelo app e
 * nunca passam pela IA. Cada passo confere quem pede, o papel na casa e o status, e atualiza o
 * card guardado na mensagem (e nos outros aparelhos, pelo evento).
 */
class ActionService
{
    public function __construct(private readonly ToolRegistry $tools) {}

    /**
     * Guarda a ação. Lançar, pagar e apagar ficam pendentes até a confirmação; itens de lista e
     * lembretes são gravados na hora, com prazo para desfazer.
     */
    public function propose(ActionTool $tool, PreparedAction $prepared, ToolContext $context): ToolResult
    {
        $immediate = $tool->tier() === ToolTier::Immediate;

        // Numa transação só: se gravar a imediata falhar, não sobra uma ação pela metade.
        $action = DB::transaction(function () use ($tool, $prepared, $context, $immediate) {
            $action = new PendingAction;
            $action->forceFill([
                'household_id' => $context->household->id,
                'user_id' => $context->user->id,
                'message_id' => $context->reply?->id,
                'tool' => $tool->name(),
                'tier' => $tool->tier(),
                'status' => ActionStatus::Pending,
                'payload' => $prepared->payload,
                'expires_at' => $immediate ? null : now()->addMinutes(config('assistant.actions.confirm_minutes')),
            ])->save();

            return $immediate ? $this->execute($action, $tool, $action->payload, $context) : $action;
        });

        return new ToolResult(
            ['status' => $immediate ? 'done' : 'awaiting_confirmation', 'summary' => $prepared->summary],
            [$this->card($action, $tool, $context)],
        );
    }

    /**
     * Grava o que a pessoa conferiu. Com $edited, valida as edições do card com as mesmas regras do
     * app; sem, usa o payload guardado. A trava na linha faz dois toques gravarem uma vez só.
     *
     * @return array o card atualizado
     */
    public function confirm(PendingAction $action, User $user, ?array $edited): array
    {
        [$tool, $context] = $this->resolve($action, $user, writing: true);
        $this->expireIfStale($action, $tool, $context);

        $action = DB::transaction(function () use ($action, $tool, $context, $edited) {
            $action = PendingAction::query()->lockForUpdate()->findOrFail($action->id);
            $this->ensureStatus($action, ActionStatus::Pending);

            // As explícitas (apagar) não têm o que editar: vale o que foi mostrado no card.
            $payload = $edited === null || $action->tier === ToolTier::Explicit
                ? $action->payload
                : $tool->validatePayload($edited, $context);

            return $this->execute($action, $tool, $payload, $context);
        });

        return $this->refreshCard($action, $tool, $context);
    }

    /** @return array o card atualizado */
    public function cancel(PendingAction $action, User $user): array
    {
        [$tool, $context] = $this->resolve($action, $user, writing: false);

        $action = DB::transaction(function () use ($action) {
            $action = PendingAction::query()->lockForUpdate()->findOrFail($action->id);
            $this->ensureStatus($action, ActionStatus::Pending);
            $action->forceFill(['status' => ActionStatus::Cancelled])->save();

            return $action;
        });

        return $this->refreshCard($action, $tool, $context);
    }

    /** Desfaz uma ação imediata dentro do prazo. @return array o card atualizado */
    public function undo(PendingAction $action, User $user): array
    {
        [$tool, $context] = $this->resolve($action, $user, writing: true);

        $action = DB::transaction(function () use ($action, $tool, $context) {
            $action = PendingAction::query()->lockForUpdate()->findOrFail($action->id);

            if ($action->tier !== ToolTier::Immediate) {
                throw ValidationException::withMessages(['action' => __('assistant.actions.not_undoable')]);
            }

            $this->ensureStatus($action, ActionStatus::Executed);

            if (! $action->canUndo()) {
                throw ValidationException::withMessages(['action' => __('assistant.actions.undo_expired')]);
            }

            $tool->undo($action->result ?? [], $context);
            $action->forceFill(['status' => ActionStatus::Undone, 'undo_until' => null])->save();

            return $action;
        });

        return $this->refreshCard($action, $tool, $context);
    }

    /** O card completo: o conteúdo da ferramenta mais o pedaço "action" com o status. */
    public function card(PendingAction $action, ActionTool $tool, ToolContext $context): array
    {
        return [...$tool->card($action->payload, $action->result, $context), 'action' => $action->toCard()];
    }

    private function execute(PendingAction $action, ActionTool $tool, array $payload, ToolContext $context): PendingAction
    {
        $result = $tool->execute($payload, $context);
        $immediate = $action->tier === ToolTier::Immediate;

        $action->forceFill([
            'payload' => $payload,
            'result' => $result,
            'status' => ActionStatus::Executed,
            'executed_at' => now(),
            'undo_until' => $immediate ? now()->addSeconds(config('assistant.actions.undo_seconds')) : null,
        ])->save();

        return $action;
    }

    /**
     * Só quem pediu mexe na ação, e ela roda na casa onde foi criada (mesmo que a pessoa tenha
     * trocado de casa depois). O papel é conferido de novo: quem virou "só leitura" não grava.
     *
     * @return array{0: ActionTool, 1: ToolContext}
     */
    private function resolve(PendingAction $action, User $user, bool $writing): array
    {
        $role = $action->user_id === $user->id ? $user->roleIn($action->household) : null;

        // Ação de outra pessoa, ou de uma casa de que ela saiu: para ela, não existe.
        abort_if($role === null, 404);
        abort_if($writing && ! $role->canWrite(), 403);

        app(HouseholdContext::class)->set($action->household, $role, $user);

        $tool = $this->tools->find($action->tool);

        if (! $tool instanceof ActionTool) {
            throw new LogicException("A ferramenta {$action->tool} não grava nada.");
        }

        return [$tool, new ToolContext($user, $action->household, $role, CarbonImmutable::now($user->timezone), $action->message)];
    }

    /** Pendente há mais de 30 minutos: marca como expirada (e atualiza o card) antes de recusar. */
    private function expireIfStale(PendingAction $action, ActionTool $tool, ToolContext $context): void
    {
        if (! $action->isStale()) {
            return;
        }

        $updated = PendingAction::query()
            ->whereKey($action->id)
            ->where('status', ActionStatus::Pending)
            ->update(['status' => ActionStatus::Expired, 'updated_at' => now()]);

        if ($updated > 0) {
            $this->refreshCard($action->refresh(), $tool, $context);
        }

        throw ValidationException::withMessages(['action' => __('assistant.actions.expired')]);
    }

    private function ensureStatus(PendingAction $action, ActionStatus $expected): void
    {
        if ($action->status !== $expected) {
            throw ValidationException::withMessages(['action' => __("assistant.actions.{$action->status->value}")]);
        }
    }

    /**
     * Troca o card desta ação dentro da mensagem: ao reabrir o chat, ele mostra o que aconteceu
     * ("Confirmado", os valores que ficaram), não um botão que já não vale.
     */
    private function refreshCard(PendingAction $action, ActionTool $tool, ToolContext $context): array
    {
        $card = $this->card($action, $tool, $context);
        $message = $action->message;

        if ($message !== null) {
            $metadata = $message->metadata ?? [];
            $metadata['cards'] = array_map(
                fn (array $existing) => ($existing['action']['id'] ?? null) === $action->ulid ? $card : $existing,
                $metadata['cards'] ?? [],
            );
            $message->forceFill(['metadata' => $metadata])->save();
        }

        // Sem o Reverb, só o outro aparelho deixa de ver na hora: a ação já foi gravada e a
        // resposta desta requisição leva o card atualizado.
        try {
            event(new ActionUpdated($action->user_id, $action->message_id, $card));
        } catch (Throwable $error) {
            report($error);
        }

        return $card;
    }
}
