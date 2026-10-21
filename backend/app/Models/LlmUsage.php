<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma rodada de IA: quem perguntou, o modelo, os tokens, o tempo e o custo estimado. É um registro
 * da operação (custo e erros), não um dado da casa, por isso não passa pelo filtro de casa.
 */
#[Fillable([
    'user_id', 'household_id', 'message_id', 'provider', 'model', 'purpose', 'round', 'input_tokens', 'output_tokens',
    'thinking_tokens', 'cached_tokens', 'latency_ms', 'cost_micros', 'error',
])]
class LlmUsage extends Model
{
    protected $table = 'llm_usage';

    // Só a data de criação: a linha nunca muda depois de gravada.
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'thinking_tokens' => 'integer',
            'cached_tokens' => 'integer',
            'latency_ms' => 'integer',
            'cost_micros' => 'integer',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
