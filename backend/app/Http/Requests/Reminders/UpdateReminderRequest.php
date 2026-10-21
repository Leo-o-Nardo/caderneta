<?php

namespace App\Http\Requests\Reminders;

use App\Enums\Recurrence;
use App\Enums\Visibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReminderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'text' => ['sometimes', 'required', 'string', 'max:160'],
            'remind_at' => ['sometimes', 'required', 'date_format:Y-m-d H:i,Y-m-d'],
            'recurrence' => ['sometimes', Rule::enum(Recurrence::class)],
            'every' => ['sometimes', 'integer', 'between:1,365'],
            'visibility' => ['sometimes', Rule::enum(Visibility::class)],
        ];
    }
}
