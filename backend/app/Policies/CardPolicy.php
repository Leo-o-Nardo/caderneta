<?php

namespace App\Policies;

use App\Models\Card;
use App\Models\User;

/** Todos da casa veem todos os cartões, mas só o dono altera o seu. */
class CardPolicy
{
    public function update(User $user, Card $card): bool
    {
        return $card->user_id === $user->id;
    }

    public function delete(User $user, Card $card): bool
    {
        return $card->user_id === $user->id;
    }
}
