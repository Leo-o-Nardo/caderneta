<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Um aviso mandado a uma pessoa: a mensagem no Chat e a chave que impede repetir (ver AlertService). */
#[Fillable(['user_id', 'household_id', 'message_id', 'kind', 'key'])]
class Alert extends Model
{
    // Só a data de criação: a linha nunca muda depois de gravada (a não ser o message_id).
    public const UPDATED_AT = null;

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
