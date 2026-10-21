<?php

namespace App\Models;

use App\Enums\MessageRole;
use App\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['role', 'content', 'status', 'metadata'])]
class Message extends Model
{
    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
            'status' => MessageStatus::class,
            'metadata' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** Os cards que o app desenha embaixo do texto (gráfico, confirmação…). */
    public function cards(): array
    {
        return $this->metadata['cards'] ?? [];
    }
}
