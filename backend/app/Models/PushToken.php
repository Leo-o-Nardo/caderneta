<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Um celular que recebe os avisos como push, ligado ao login de onde se registrou. */
#[Fillable(['user_id', 'personal_access_token_id', 'token', 'platform'])]
class PushToken extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
