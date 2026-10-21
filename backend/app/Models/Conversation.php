<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A conversa de uma pessoa com o assistente dentro de uma casa. Cada casa tem a sua: trocar de
 * casa não mistura o contexto ("a fatura" de uma casa não é a da outra). "Limpar conversa"
 * arquiva a atual, que fica guardada, e a próxima mensagem começa outra.
 */
class Conversation extends Model
{
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'alerts_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** A conversa em andamento, ou null se a pessoa ainda não falou nada (ou acabou de limpar). */
    public static function activeFor(User $user, Household $household): ?self
    {
        return static::query()
            ->where('user_id', $user->id)
            ->where('household_id', $household->id)
            ->whereNull('archived_at')
            ->first();
    }

    /** A conversa em andamento, criada na primeira mensagem. */
    public static function currentFor(User $user, Household $household): self
    {
        return static::activeFor($user, $household)
            ?? static::query()->forceCreate(['user_id' => $user->id, 'household_id' => $household->id]);
    }

    /**
     * "Limpar conversa": a conversa sai da tela e a IA para de vê-la, mas tudo continua guardado.
     * Os cards que ainda esperavam confirmação simplesmente expiram.
     */
    public function archive(): void
    {
        $this->forceFill(['archived_at' => now()])->save();
    }
}
