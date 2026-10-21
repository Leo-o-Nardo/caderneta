<?php

namespace App\Assistant;

use App\Assistant\Llm\LlmException;
use App\Assistant\Llm\LlmMessage;
use App\Assistant\Llm\LlmProvider;
use App\Assistant\Llm\LlmRequest;
use App\Assistant\Llm\ToolCall;
use App\Assistant\Tools\AssistantTool;
use App\Assistant\Tools\ToolContext;
use App\Assistant\Tools\ToolRegistry;
use App\Assistant\Tools\ToolResult;
use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Models\Message;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Um turno do assistente: a pergunta já está salva e $reply é a resposta vazia ("pensando…").
 * A IA pode pedir ferramentas algumas vezes antes de responder; cada pedido vira um status para o
 * app, roda dentro da casa de quem perguntou e o resultado volta para a IA. No fim, o texto e os
 * cards ficam salvos na resposta.
 */
final class TurnRunner
{
    private const ACTION_NOTES = [
        'pending' => 'esperando a confirmação',
        'executed' => 'confirmada e gravada',
        'cancelled' => 'cancelada pela pessoa',
        'expired' => 'expirou sem confirmação',
        'undone' => 'desfeita pela pessoa',
    ];

    public function __construct(
        private readonly LlmProvider $llm,
        private readonly ToolRegistry $tools,
        private readonly UsageRecorder $usage,
    ) {}

    public function run(Message $reply, TurnObserver $observer): Message
    {
        try {
            return $this->process($reply, $observer);
        } catch (LlmException $error) {
            // IA ocupada é passageiro; os outros motivos ficam no log para investigar.
            if (! $error->retryable()) {
                report($error);
            }

            return $this->fail($reply, $observer, $error->userMessage());
        } catch (Throwable $error) {
            report($error);

            return $this->fail($reply, $observer, __('assistant.failed'));
        }
    }

    private function process(Message $reply, TurnObserver $observer): Message
    {
        $conversation = $reply->conversation;
        $user = $conversation->user;
        $household = $conversation->household;
        $role = $user->roleIn($household);

        if ($role === null) {
            return $this->fail($reply, $observer, __('assistant.not_member'));
        }

        // Fora de uma requisição ninguém preenche o contexto: sem ele, os filtros de casa e de
        // privados não sabem quem está perguntando.
        app(HouseholdContext::class)->set($household, $role, $user);

        $today = CarbonImmutable::now($user->timezone);
        $context = AssistantContext::for($user, $household, $role, $today);
        // As ações propostas neste turno ficam ligadas a esta resposta (é nela que o card aparece).
        $toolContext = new ToolContext($user, $household, $role, $today, $reply);
        $available = $this->tools->availableFor($role);

        $request = new LlmRequest(AssistantPrompt::system($context), $this->history($reply), $available, $context);
        $cards = [];
        $trace = [];
        $text = null;
        // O que a IA disse antes de pedir ferramentas ("vou conferir…"): já foi para a tela, então
        // fica na resposta, antes do texto final.
        $spoken = '';

        for ($round = 1; $round <= config('assistant.max_rounds'); $round++) {
            $started = microtime(true);

            try {
                $response = $this->llm->generate($request, fn (string $chunk) => $observer->delta($reply, $chunk));
            } catch (LlmException $error) {
                $this->usage->failed($reply, $round, $error, self::elapsed($started));

                // Uma ação já foi preparada nesta resposta: salva com o card e um aviso, em vez de
                // perder o card (a ação pendente ficaria sem onde ser confirmada).
                if ($cards !== []) {
                    $text = $spoken.__('assistant.llm.partial');
                    break;
                }

                throw $error;
            }

            $this->usage->record($reply, $round, $response, self::elapsed($started));

            if (! $response->wantsTools()) {
                $text = $spoken.$response->text;
                break;
            }

            if ($response->text !== null) {
                $spoken .= $response->text."\n\n";
                $observer->delta($reply, "\n\n");
            }

            $request = $request->with(LlmMessage::toolCalls($response->toolCalls, $response->text));

            foreach ($response->toolCalls as $call) {
                $tool = $this->find($available, $call->name);

                if ($tool) {
                    $observer->status($reply, $tool->status());
                }

                $result = $tool ? $this->runTool($tool, $call, $toolContext) : ToolResult::error(__('assistant.unknown_tool'));
                $request = $request->with(LlmMessage::toolResult($call, $result->data));
                $cards = [...$cards, ...$result->cards];
                $trace[] = array_filter(['name' => $call->name, 'args' => $call->args, 'error' => $result->data['error'] ?? null]);
            }
        }

        $reply->forceFill([
            'content' => $text ?? __('assistant.too_many_rounds'),
            'status' => MessageStatus::Completed,
            'metadata' => [...($reply->metadata ?? []), 'cards' => $cards, 'tools' => $trace],
        ])->save();

        $observer->completed($reply);

        return $reply;
    }

    /**
     * As últimas mensagens da conversa antes desta resposta, da mais antiga para a mais nova. A
     * pergunta que acabou de chegar é a última delas.
     *
     * @return list<LlmMessage>
     */
    private function history(Message $reply): array
    {
        return $reply->conversation->messages()
            ->where('id', '<', $reply->id)
            ->where('status', MessageStatus::Completed)
            ->whereIn('role', [MessageRole::User, MessageRole::Assistant, MessageRole::Event])
            ->whereNotNull('content')
            ->latest('id')
            ->limit(config('assistant.history'))
            ->get()
            ->reverse()
            ->map(fn (Message $message) => match ($message->role) {
                MessageRole::User => LlmMessage::user($message->content),
                // Os avisos do app ("Luz vence amanhã") entram para a IA entender o "paguei" seguinte.
                MessageRole::Event => LlmMessage::assistant("[aviso do app] {$message->content}"),
                default => LlmMessage::assistant($message->content.$this->actionNotes($message)),
            })
            ->values()
            ->all();
    }

    /**
     * O que a pessoa fez com os cards desta resposta. A confirmação passa pelo app, não pela IA: sem
     * isto, na pergunta seguinte ela não saberia se o lançamento foi gravado ou cancelado.
     */
    private function actionNotes(Message $message): string
    {
        $notes = [];

        foreach ($message->cards() as $card) {
            $status = $card['action']['status'] ?? null;

            if ($status !== null) {
                $notes[] = sprintf('[ação: %s %s]', $card['type'], self::ACTION_NOTES[$status] ?? $status);
            }
        }

        return $notes ? "\n".implode("\n", $notes) : '';
    }

    /** Uma ferramenta que quebra não derruba a resposta: a IA recebe o erro e avisa a pessoa. */
    private function runTool(AssistantTool $tool, ToolCall $call, ToolContext $context): ToolResult
    {
        try {
            return $tool->handle($call->args, $context);
        } catch (Throwable $error) {
            report($error);

            return ToolResult::error(__('assistant.tool_failed'));
        }
    }

    /** Milissegundos desde $started (de microtime(true)). */
    private static function elapsed(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }

    /** @param  list<AssistantTool>  $available */
    private function find(array $available, string $name): ?AssistantTool
    {
        return (new Collection($available))->first(fn (AssistantTool $tool) => $tool->name() === $name);
    }

    private function fail(Message $reply, TurnObserver $observer, string $message): Message
    {
        $reply->forceFill([
            'content' => $message,
            'status' => MessageStatus::Failed,
        ])->save();

        $observer->failed($reply);

        return $reply;
    }
}
