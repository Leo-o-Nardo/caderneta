<?php

namespace App\Console\Commands;

use App\Assistant\Observers\ConsoleObserver;
use App\Assistant\TurnRunner;
use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Jobs\TranscribeVoice;
use App\Models\Conversation;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Manda um arquivo de áudio ao assistente pelo mesmo caminho da mensagem de voz do app, sem
 * precisar falar no microfone: transcreve, mostra o que foi entendido e a resposta chegando. A
 * mensagem também aparece no Chat do app (é a mesma conversa).
 *
 *   php artisan assistant:voice storage/app/teste-voz.wav --user=leo@example.com
 */
class AssistantVoice extends Command
{
    protected $signature = 'assistant:voice
        {file : O arquivo de áudio (.wav, .mp3, .m4a, .aac, .ogg ou .flac), com o caminho a partir da pasta backend}
        {--user= : E-mail de quem fala (padrão: o primeiro usuário)}';

    protected $description = 'Manda um arquivo de áudio ao assistente, como a mensagem de voz do app';

    // Os formatos que o Gemini aceita, pela extensão do arquivo.
    private const MIME_TYPES = [
        'wav' => 'audio/wav',
        'mp3' => 'audio/mp3',
        'm4a' => 'audio/m4a',
        'aac' => 'audio/aac',
        'ogg' => 'audio/ogg',
        'flac' => 'audio/flac',
    ];

    public function handle(TurnRunner $runner, HouseholdService $households): int
    {
        $path = is_file($this->argument('file')) ? $this->argument('file') : base_path($this->argument('file'));

        if (! is_file($path)) {
            $this->error("Não encontrei o arquivo {$this->argument('file')}. O caminho é a partir da pasta backend (ex.: storage/app/teste-voz.wav).");

            return self::FAILURE;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = self::MIME_TYPES[$extension] ?? null;

        if ($mime === null) {
            $this->error('Formato não aceito. Use .wav, .mp3, .m4a, .aac, .ogg ou .flac.');

            return self::FAILURE;
        }

        $email = $this->option('user');
        $user = $email
            ? User::query()->where('email', $email)->first()
            : User::query()->orderBy('id')->first();

        if ($user === null) {
            $this->error($email ? "Nenhum usuário com o e-mail {$email}." : 'Nenhum usuário cadastrado. Rode o seeder de demonstração.');

            return self::FAILURE;
        }

        $household = $households->ensureCurrent($user);
        $conversation = Conversation::currentFor($user, $household);

        // Uma cópia vai para a pasta das gravações: a transcrição apaga a cópia no fim, e o seu
        // arquivo continua onde está.
        $file = Str::ulid().'.'.$extension;
        Storage::disk('local')->put(TranscribeVoice::path($file), (string) file_get_contents($path));

        $message = $conversation->messages()->create([
            'role' => MessageRole::User,
            'status' => MessageStatus::Transcribing,
            'metadata' => ['source' => 'cli', 'kind' => 'audio', 'audio_file' => $file, 'audio_mime' => $mime],
        ]);

        $reply = $conversation->messages()->create([
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Pending,
        ]);

        $this->line("<fg=gray>{$user->name} · {$household->name}</>");
        $this->line('<fg=gray>Transcrevendo…</>');

        // Roda aqui mesmo, sem fila: primeiro a transcrição, depois o turno do assistente.
        TranscribeVoice::dispatchSync($message->id, $reply->id);

        $message->refresh();
        $reply->refresh();

        if ($message->status !== MessageStatus::Completed) {
            $this->error((string) $reply->content);

            return self::FAILURE;
        }

        $this->line("<fg=cyan>Você disse:</> {$message->content}");
        $this->newLine();

        $reply = $runner->run($reply, new ConsoleObserver($this->output));

        return $reply->status === MessageStatus::Completed ? self::SUCCESS : self::FAILURE;
    }
}
