<?php

namespace App\Console\Commands;

use App\Assistant\Observers\ConsoleObserver;
use App\Assistant\TurnRunner;
use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Console\Command;

/**
 * Faz uma pergunta ao assistente pelo terminal, como se fosse pelo app, e mostra a resposta
 * chegando. Serve para ver o assistente funcionando antes das telas e para depurar a IA real.
 *
 *   php artisan assistant:ask "quanto gastei com mercado esse mês?" --user=leo@example.com
 */
class AssistantAsk extends Command
{
    protected $signature = 'assistant:ask
        {text : A pergunta, entre aspas}
        {--user= : E-mail de quem pergunta (padrão: o primeiro usuário)}';

    protected $description = 'Faz uma pergunta ao assistente e mostra a resposta no terminal';

    public function handle(TurnRunner $runner, HouseholdService $households): int
    {
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

        $conversation->messages()->create([
            'role' => MessageRole::User,
            'content' => $this->argument('text'),
            'status' => MessageStatus::Completed,
            'metadata' => ['source' => 'cli'],
        ]);

        $reply = $conversation->messages()->create([
            'role' => MessageRole::Assistant,
            'status' => MessageStatus::Pending,
        ]);

        $this->line("<fg=gray>{$user->name} · {$household->name}</>");
        $this->newLine();

        // Roda aqui mesmo, sem fila: o terminal acompanha o turno inteiro.
        $reply = $runner->run($reply, new ConsoleObserver($this->output));

        return $reply->status === MessageStatus::Completed ? self::SUCCESS : self::FAILURE;
    }
}
