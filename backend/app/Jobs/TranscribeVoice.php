<?php

namespace App\Jobs;

use App\Assistant\AssistantContext;
use App\Assistant\Llm\LlmException;
use App\Assistant\UsageRecorder;
use App\Assistant\Voice\Transcriber;
use App\Enums\MessageStatus;
use App\Events\Assistant\AssistantFailed;
use App\Events\Assistant\MessageTranscribed;
use App\Models\Household;
use App\Models\Message;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Transforma a mensagem de voz em texto e apaga o áudio, dê certo ou não. Roda antes do
 * ProcessTurn (os dois vão encadeados na fila): com o texto pronto, o assistente responde como se
 * a pessoa tivesse digitado. Sem texto (sem fala, ou o serviço falhou), a mensagem e a resposta
 * ficam como "falhou" e o ProcessTurn, ao ver a resposta já resolvida, não faz nada.
 */
final class TranscribeVoice implements ShouldQueue
{
    use Queueable;

    // Uma nova tentativa sozinha mandaria o áudio de novo; quem decide repetir é a pessoa.
    public int $tries = 1;

    public int $timeout = 90;

    public function __construct(
        public readonly int $messageId,
        public readonly int $replyId,
    ) {
        $this->onQueue('assistant');
    }

    /** Onde o áudio fica até ser transcrito: storage/app/private/voice, fora de qualquer URL pública. */
    public static function path(string $file): string
    {
        return "voice/{$file}";
    }

    public function handle(Transcriber $transcriber, UsageRecorder $usage): void
    {
        $message = Message::query()->with('conversation.user', 'conversation.household')->find($this->messageId);

        if ($message === null || $message->status !== MessageStatus::Transcribing) {
            return;
        }

        try {
            $this->transcribe($message, $transcriber, $usage);
        } finally {
            self::deleteAudio($message);
        }
    }

    /** O worker matou a tarefa (tempo esgotado, erro inesperado): nada fica "transcrevendo" para sempre. */
    public function failed(?Throwable $error): void
    {
        $message = Message::query()->with('conversation')->find($this->messageId);

        if ($message === null) {
            return;
        }

        self::deleteAudio($message);

        if ($message->status === MessageStatus::Transcribing) {
            $this->markFailed($message, __('assistant.voice.failed'));
        }
    }

    private function transcribe(Message $message, Transcriber $transcriber, UsageRecorder $usage): void
    {
        $user = $message->conversation->user;
        $household = $message->conversation->household;
        $role = $user->roleIn($household);

        if ($role === null) {
            $this->markFailed($message, __('assistant.not_member'));

            return;
        }

        // Para ler os nomes da casa (cartões, listas…) com os filtros de casa e de privados.
        app(HouseholdContext::class)->set($household, $role, $user);

        $started = microtime(true);

        try {
            $transcript = $transcriber->transcribe(
                Storage::disk('local')->path(self::path($message->metadata['audio_file'])),
                $message->metadata['audio_mime'] ?? 'audio/m4a',
                self::hints($household, AssistantContext::for($user, $household, $role, CarbonImmutable::now($user->timezone))),
            );
        } catch (LlmException $error) {
            $usage->transcriptionFailed($message, $error, self::elapsed($started));

            if (! $error->retryable()) {
                report($error);
            }

            $this->markFailed($message, __($error->retryable() ? 'assistant.voice.busy' : 'assistant.voice.failed'));

            return;
        }

        $usage->transcribed($message, $transcript, self::elapsed($started));

        if ($transcript->text === '') {
            $this->markFailed($message, __('assistant.voice.no_speech'));

            return;
        }

        $message->forceFill([
            'content' => mb_substr($transcript->text, 0, (int) config('assistant.max_chars')),
            'status' => MessageStatus::Completed,
        ])->save();

        self::broadcast(new MessageTranscribed($message->conversation->user_id, $message));
    }

    /**
     * Não deu para transcrever: a mensagem fica sem texto (o app mostra "Não entendi o áudio") e a
     * resposta explica o que fazer.
     */
    private function markFailed(Message $message, string $explanation): void
    {
        $message->forceFill(['status' => MessageStatus::Failed])->save();
        self::broadcast(new MessageTranscribed($message->conversation->user_id, $message));

        $reply = Message::query()->find($this->replyId);

        if ($reply?->status === MessageStatus::Pending) {
            $reply->forceFill(['content' => $explanation, 'status' => MessageStatus::Failed])->save();
            self::broadcast(new AssistantFailed($message->conversation->user_id, $reply));
        }
    }

    /**
     * Os nomes que a pessoa pode falar e que um transcritor escreveria errado: cartões, categorias,
     * listas, contas e receitas fixas da casa, e o primeiro nome de cada membro.
     *
     * @return list<string>
     */
    private static function hints(Household $household, AssistantContext $context): array
    {
        $names = [
            ...array_column($context->cards, 'name'),
            ...array_column($context->categories, 'name'),
            ...array_column($context->lists, 'name'),
            ...array_column($context->bills, 'name'),
            ...array_column($context->incomes, 'description'),
            ...$household->members()->pluck('name')->map(fn (string $name) => strtok($name, ' '))->all(),
        ];

        return array_values(array_unique(array_filter($names)));
    }

    private static function deleteAudio(Message $message): void
    {
        if (isset($message->metadata['audio_file'])) {
            Storage::disk('local')->delete(self::path($message->metadata['audio_file']));
        }
    }

    /** Sem o Reverb, o app busca sozinho: falhar ao avisar não pode atrapalhar a transcrição. */
    private static function broadcast(object $event): void
    {
        try {
            event($event);
        } catch (Throwable $error) {
            report($error);
        }
    }

    private static function elapsed(float $started): int
    {
        return (int) round((microtime(true) - $started) * 1000);
    }
}
