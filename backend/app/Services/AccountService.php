<?php

namespace App\Services;

use App\Jobs\TranscribeVoice;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Exclui a conta, pelo app (Configurações › Conta) ou pela página /excluir-conta. As lojas exigem
 * os dois caminhos.
 *
 * Sai o que é só da pessoa: o login em todos os celulares (e o push junto), as conversas com o
 * assistente, os avisos, os lembretes, os cartões e os convites que ela mandou. O que é da casa fica
 * para quem continua nela: os lançamentos, as contas e as listas perdem só o "quem lançou" (as
 * chaves created_by são nullOnDelete). As casas passam de dono antes (HouseholdService::removeEverywhere).
 */
class AccountService
{
    public function __construct(private readonly HouseholdService $households) {}

    public function delete(User $user): void
    {
        $this->households->removeEverywhere($user);

        // Um áudio que ainda espera a transcrição: a mensagem some com a conversa, o arquivo não.
        $audios = Message::query()
            ->whereHas('conversation', fn ($query) => $query->where('user_id', $user->id))
            ->whereNotNull('metadata->audio_file')
            ->pluck('metadata')
            ->map(fn (array $metadata) => TranscribeVoice::path($metadata['audio_file']));

        DB::transaction(function () use ($user) {
            // Os logins do Sanctum não têm chave estrangeira (morphs): saem à mão.
            $user->tokens()->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->delete();
        });

        Storage::disk('local')->delete($audios->all());
    }
}
