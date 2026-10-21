<?php

namespace App\Assistant\Tools;

use App\Enums\Visibility;
use App\Models\Reminder;

/** Os lembretes ativos, do mais próximo ao mais distante (os privados de outras pessoas não aparecem). */
final class ListReminders extends Tool
{
    public function name(): string
    {
        return 'list_reminders';
    }

    public function description(): string
    {
        return 'Os próximos lembretes (IPVA, vacina do pet, trocar o filtro…), dos mais próximos aos mais distantes, '
            .'incluindo os atrasados.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
        ];
    }

    public function status(): string
    {
        return 'Vendo os lembretes…';
    }

    protected function rules(): array
    {
        return [];
    }

    protected function run(array $args, ToolContext $context): ToolResult
    {
        $reminders = Reminder::query()->whereNull('done_at')->orderBy('remind_at')->limit(10)->get();

        return new ToolResult([
            'reminders' => $reminders->map(function (Reminder $reminder) use ($context) {
                $at = $reminder->remind_at->setTimezone($context->timezone());

                return array_filter([
                    'text' => $reminder->text,
                    'when' => $at->format('d/m').' às '.$at->format('H:i'),
                    'is_overdue' => $reminder->remind_at->isPast(),
                    'repeats' => $reminder->recurrence->label($reminder->every),
                    'shared_with_household' => $reminder->visibility === Visibility::Household,
                ], fn ($value) => $value !== null);
            })->all(),
        ]);
    }
}
