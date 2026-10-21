<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
| Os canais privados. Cada um diz quem pode escutar; o app pede a autorização em
| POST /api/v1/broadcasting/auth, com o mesmo token das outras chamadas.
*/

// O canal de cada pessoa: respostas do assistente e cards que mudaram. Só ela escuta.
Broadcast::channel('user.{id}', fn (User $user, string $id) => $user->id === (int) $id);
