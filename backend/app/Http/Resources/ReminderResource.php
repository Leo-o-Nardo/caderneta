<?php

namespace App\Http\Resources;

use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Reminder */
class ReminderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $timezone = $request->user()->timezone;

        return [
            'id' => $this->id,
            'text' => $this->text,
            // No fuso do usuário, com o deslocamento: "2026-10-20T09:00:00-03:00".
            'remind_at' => $this->remind_at->setTimezone($timezone)->toIso8601String(),
            'recurrence' => $this->recurrence,
            'every' => $this->every,
            'visibility' => $this->visibility,
            'is_done' => $this->done_at !== null,
            'is_overdue' => $this->done_at === null && $this->remind_at->isPast(),
            'created_by' => $this->creator ? ['id' => $this->creator->id, 'name' => $this->creator->name] : null,
        ];
    }
}
