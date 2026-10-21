<?php

namespace App\Http\Requests\Reminders;

use App\Enums\Recurrence;
use App\Enums\Visibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReminderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:160'],
            // Horário local do usuário. Só a data vira 9h.
            'remind_at' => ['required', 'date_format:Y-m-d H:i,Y-m-d'],
            'recurrence' => ['sometimes', Rule::enum(Recurrence::class)],
            'every' => ['sometimes', 'integer', 'between:1,365'],
            'visibility' => ['sometimes', Rule::enum(Visibility::class)],
        ];
    }
}
