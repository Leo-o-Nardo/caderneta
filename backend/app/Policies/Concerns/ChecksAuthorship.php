<?php

namespace App\Policies\Concerns;

use App\Enums\HouseholdRole;
use App\Models\User;
use App\Support\HouseholdContext;

trait ChecksAuthorship
{
    /**
     * Quem criou pode alterar o que é seu; o dono da casa pode corrigir o de todos. O registro
     * já é da casa do contexto (o filtro automático garante), então o papel do contexto vale para ele.
     */
    private function canChange(User $user, ?int $authorId): bool
    {
        if ($authorId === $user->id) {
            return true;
        }

        $context = app(HouseholdContext::class);

        return $context->has() && $context->role() === HouseholdRole::Owner;
    }
}
