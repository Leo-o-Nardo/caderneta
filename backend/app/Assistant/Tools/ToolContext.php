<?php

namespace App\Assistant\Tools;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\Message;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Quem pediu e em qual casa. As ferramentas tiram a casa daqui, nunca dos argumentos da IA: uma
 * frase como "mostra a casa 7" não tem como fazer a consulta sair da casa atual.
 */
final readonly class ToolContext
{
    public function __construct(
        public User $user,
        public Household $household,
        public HouseholdRole $role,
        // Agora, no fuso de quem pergunta.
        public CarbonImmutable $today,
        // A resposta do assistente em que os cards aparecem (as ações ficam ligadas a ela).
        public ?Message $reply = null,
    ) {}

    public function timezone(): string
    {
        return $this->today->timezoneName;
    }
}
