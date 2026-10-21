<?php

namespace App\Models;

use App\Enums\Recurrence;
use App\Enums\Visibility;
use App\Models\Concerns\BelongsToHousehold;
use App\Models\Concerns\HasVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Um lembrete. Privado por padrão (só quem criou vê); pode ser da casa. Os horários ficam em
 * UTC no banco: quem converte do fuso do usuário é o ReminderService.
 */
#[Fillable(['text', 'recurrence', 'every', 'visibility'])]
class Reminder extends Model
{
    use BelongsToHousehold, HasVisibility;

    protected $attributes = [
        'recurrence' => 'none',
        'every' => 1,
        'visibility' => 'private',
    ];

    protected function casts(): array
    {
        return [
            'remind_at' => 'immutable_datetime',
            'anchor_at' => 'immutable_datetime',
            'recurrence' => Recurrence::class,
            'every' => 'integer',
            'visibility' => Visibility::class,
            'done_at' => 'immutable_datetime',
            'last_notified_at' => 'immutable_datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
