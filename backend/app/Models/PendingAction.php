<?php

namespace App\Models;

use App\Assistant\Tools\ToolTier;
use App\Enums\ActionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma ação proposta pelo assistente. O payload guarda os dados já validados: confirmar grava
 * exatamente isso (com as edições do card), sem perguntar nada de novo à IA.
 */
class PendingAction extends Model
{
    use HasUlids;

    protected function casts(): array
    {
        return [
            'tier' => ToolTier::class,
            'status' => ActionStatus::class,
            'payload' => 'array',
            'result' => 'array',
            'expires_at' => 'immutable_datetime',
            'undo_until' => 'immutable_datetime',
            'executed_at' => 'immutable_datetime',
        ];
    }

    /** O ulid é gerado sozinho, mas a chave da tabela continua sendo o id numérico. */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /** Nas URLs (/actions/{ulid}/confirm), a ação é achada pelo ulid. */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function isStale(): bool
    {
        return $this->status === ActionStatus::Pending && $this->expires_at?->isPast();
    }

    public function canUndo(): bool
    {
        return $this->status === ActionStatus::Executed && $this->undo_until?->isFuture();
    }

    /** O pedaço "action" de todo card: o app desenha os botões (confirmar, desfazer) a partir daqui. */
    public function toCard(): array
    {
        return [
            'id' => $this->ulid,
            'tool' => $this->tool,
            'tier' => $this->tier->value,
            'status' => $this->status->value,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'undo_until' => $this->undo_until?->toIso8601String(),
        ];
    }
}
