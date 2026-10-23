<?php

namespace App\Http\Controllers;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Http\Requests\Assistant\SendMessageRequest;
use App\Http\Requests\Assistant\SendVoiceRequest;
use App\Http\Resources\MessageResource;
use App\Jobs\ProcessTurn;
use App\Jobs\TranscribeVoice;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\ResponseReported;
use App\Support\HouseholdContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A conversa com o assistente na casa atual. Quem é só leitura também conversa: só não recebe as
 * ferramentas que gravam (ver ToolRegistry::availableFor).
 */
class ConversationController extends Controller
{
    /** As mensagens da conversa em andamento, da mais nova para a mais antiga (o app rola para cima). */
    public function show(Request $request, HouseholdContext $context): AnonymousResourceCollection
    {
        $conversation = Conversation::activeFor($request->user(), $context->household());

        $messages = Message::query()
            ->where('conversation_id', $conversation?->id ?? 0)
            ->orderByDesc('id')
            ->cursorPaginate(30);

        return MessageResource::collection($messages);
    }

    /**
     * Grava a pergunta e uma resposta vazia, e põe o turno na fila. Responde na hora: o texto da
     * resposta chega depois pelo canal do usuário (status, pedaços, resposta pronta).
     */
    public function store(SendMessageRequest $request, HouseholdContext $context): JsonResponse
    {
        $user = $request->user();
        $this->ensureUnderDailyLimit($user);

        [$message, $reply] = DB::transaction(function () use ($request, $context, $user) {
            $conversation = Conversation::currentFor($user, $context->household());

            $message = $conversation->messages()->create([
                'role' => MessageRole::User,
                'content' => $request->validated('text'),
                'status' => MessageStatus::Completed,
                'metadata' => ['source' => $request->validated('source') ?? 'chat'],
            ]);

            $reply = $conversation->messages()->create([
                'role' => MessageRole::Assistant,
                'status' => MessageStatus::Pending,
            ]);

            return [$message, $reply];
        });

        ProcessTurn::dispatch($reply->id);

        return response()->json([
            'message' => new MessageResource($message),
            // Com a fila rodando à parte, ainda "pending"; nos testes (fila síncrona), já pronta.
            'reply' => new MessageResource($reply->refresh()),
        ], 202);
    }

    /**
     * Uma mensagem de voz. O áudio fica numa pasta privada só até virar texto; a mensagem nasce
     * "transcrevendo" e a resposta "pensando". Na fila, a transcrição roda primeiro e o turno do
     * assistente logo depois, como se a pessoa tivesse digitado.
     */
    public function voice(SendVoiceRequest $request, HouseholdContext $context): JsonResponse
    {
        $user = $request->user();
        $this->ensureUnderDailyLimit($user, 'audio');

        $audio = $request->file('audio');
        // O Gemini aceita .m4a e .aac; o que importa é o formato de dentro, não o nome do arquivo.
        $mime = in_array($audio->getMimeType(), ['audio/aac', 'audio/x-hx-aac-adts'], true) ? 'audio/aac' : 'audio/m4a';
        // Grava antes de criar as mensagens: se algo falhar no meio, sobra só um arquivo, que a
        // faxina de hora em hora apaga (ver routes/console.php).
        $file = Str::ulid().($mime === 'audio/aac' ? '.aac' : '.m4a');
        $audio->storeAs('voice', $file, 'local');

        [$message, $reply] = DB::transaction(function () use ($request, $context, $user, $file, $mime) {
            $conversation = Conversation::currentFor($user, $context->household());

            $message = $conversation->messages()->create([
                'role' => MessageRole::User,
                'status' => MessageStatus::Transcribing,
                'metadata' => [
                    'source' => $request->validated('source') ?? 'chat',
                    'kind' => 'audio',
                    'duration_ms' => (int) $request->validated('duration_ms'),
                    'audio_file' => $file,
                    'audio_mime' => $mime,
                ],
            ]);

            $reply = $conversation->messages()->create([
                'role' => MessageRole::Assistant,
                'status' => MessageStatus::Pending,
            ]);

            return [$message, $reply];
        });

        Bus::chain([new TranscribeVoice($message->id, $reply->id), new ProcessTurn($reply->id)])->dispatch();

        return response()->json([
            // Com a fila rodando à parte, "transcribing" e "pending"; nos testes (fila síncrona), prontas.
            'message' => new MessageResource($message->refresh()),
            'reply' => new MessageResource($reply->refresh()),
        ], 202);
    }

    /**
     * "Limpar conversa": arquiva a conversa atual, que fica guardada, e a próxima mensagem começa
     * outra. Para o app, a conversa simplesmente fica vazia.
     */
    public function clear(Request $request, HouseholdContext $context): Response
    {
        Conversation::activeFor($request->user(), $context->household())?->archive();

        return response()->noContent();
    }

    /**
     * "Reportar resposta": a pessoa marca uma resposta do assistente como ofensiva, errada ou
     * estranha (o Google Play exige isso em apps com IA que gera texto). A resposta fica marcada e
     * o administrador recebe por e-mail, com a pergunta que veio antes. Reportar de novo não manda outro.
     */
    public function report(Request $request, Message $message): Response
    {
        $conversation = $message->conversation;

        // Só as respostas prontas do assistente, e só nas conversas da própria pessoa.
        abort_unless(
            $conversation->user_id === $request->user()->id
                && $message->role === MessageRole::Assistant
                && $message->status === MessageStatus::Completed,
            404,
        );

        if (isset($message->metadata['reported_at'])) {
            return response()->noContent();
        }

        $message->update(['metadata' => [...($message->metadata ?? []), 'reported_at' => now()->toIso8601String()]]);

        $question = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', MessageRole::User)
            ->where('id', '<', $message->id)
            ->latest('id')
            ->first();
        $admin = config('access.admin_email');

        if (blank($admin)) {
            Log::warning('ADMIN_EMAIL não configurado: a resposta reportada não foi enviada.', ['message_id' => $message->id]);
        } else {
            Notification::route('mail', $admin)->notify(new ResponseReported($message, $question));
        }

        return response()->noContent();
    }

    /**
     * Conta as mensagens enviadas hoje (digitadas e de voz), no fuso da pessoa, em todas as casas
     * dela. As conversas arquivadas também contam: limpar a conversa não zera o limite. O erro sai
     * no campo $field ("text" ou "audio").
     */
    private function ensureUnderDailyLimit(User $user, string $field = 'text'): void
    {
        $limit = config('assistant.daily_limit');
        // O banco guarda em UTC: o começo do dia no fuso da pessoa precisa ir convertido.
        $startOfDay = CarbonImmutable::now($user->timezone)->startOfDay()->utc();

        $sent = Message::query()
            ->where('role', MessageRole::User)
            ->where('created_at', '>=', $startOfDay)
            ->whereHas('conversation', fn ($query) => $query->where('user_id', $user->id))
            ->count();

        if ($sent >= $limit) {
            throw ValidationException::withMessages([$field => __('assistant.daily_limit', ['limit' => $limit])]);
        }
    }
}
