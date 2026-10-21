<?php

namespace App\Http\Resources;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Message */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            // Vazio enquanto a resposta está sendo preparada.
            'content' => $this->content,
            'status' => $this->status,
            'cards' => $this->cards(),
            // De onde a pessoa mandou: "chat" ou "home" (a captura rápida do Início).
            'source' => $this->metadata['source'] ?? null,
            // "audio" quando veio de voz (a bolha mostra o microfone e a duração); senão, "text".
            'kind' => $this->metadata['kind'] ?? 'text',
            'duration_ms' => $this->metadata['duration_ms'] ?? null,
            'created_at' => $this->created_at,
        ];
    }
}
