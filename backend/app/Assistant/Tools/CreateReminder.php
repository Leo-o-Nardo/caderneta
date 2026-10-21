<?php

namespace App\Assistant\Tools;

use App\Assistant\Actions\ActionToolBase;
use App\Assistant\Actions\PreparedAction;
use App\Assistant\Actions\RequestRules;
use App\Enums\Recurrence;
use App\Http\Requests\Reminders\StoreReminderRequest;
use App\Models\Reminder;
use App\Services\ReminderService;
use Illuminate\Validation\Rule;

/** "Me lembra de pagar o IPVA dia 20". Grava na hora, com "Desfazer". Sem horário, vale às 9h. */
final class CreateReminder extends ActionToolBase
{
    public function __construct(private readonly ReminderService $reminders) {}

    public function name(): string
    {
        return 'create_reminder';
    }

    public function description(): string
    {
        return 'Cria um lembrete ("me lembra de pagar o IPVA dia 20 às 10h", "todo mês, trocar o filtro"). '
            .'Grava na hora e o card tem "Desfazer". Sem horário dito, vale às 9h.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'text' => ['type' => 'string', 'description' => 'O que lembrar, curto: "Pagar o IPVA".'],
                'when' => ['type' => 'string', 'description' => '"AAAA-MM-DD HH:MM", ou só "AAAA-MM-DD" (vira 9h). Precisa ser no futuro.'],
                'recurrence' => ['type' => 'string', 'enum' => ['none', 'daily', 'weekly', 'monthly', 'yearly']],
                'every' => ['type' => 'integer', 'description' => 'A cada quantos dias/semanas/meses/anos (padrão 1).'],
                'shared' => ['type' => 'boolean', 'description' => 'Da casa toda (todos veem). Padrão: só de quem pediu.'],
            ],
            'required' => ['text', 'when'],
        ];
    }

    public function tier(): ToolTier
    {
        return ToolTier::Immediate;
    }

    public function status(): string
    {
        return 'Criando o lembrete…';
    }

    protected function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:160'],
            'when' => ['required', 'date_format:Y-m-d H:i,Y-m-d'],
            'recurrence' => ['nullable', Rule::enum(Recurrence::class)],
            'every' => ['nullable', 'integer', 'between:1,365'],
            'shared' => ['nullable', 'boolean'],
        ];
    }

    public function prepare(array $args, ToolContext $context): PreparedAction|ToolResult
    {
        $at = ReminderService::parseLocal($args['when'], $context->timezone());

        if ($at->isPast()) {
            return ToolResult::error('Esse horário já passou. Para quando é o lembrete?');
        }

        $recurrence = Recurrence::from($args['recurrence'] ?? 'none');
        $every = (int) ($args['every'] ?? 1);

        $payload = array_filter([
            'text' => self::capitalize($args['text']),
            'remind_at' => $args['when'],
            'recurrence' => $recurrence->value,
            'every' => $every,
            'visibility' => ($args['shared'] ?? false) ? 'household' : 'private',
        ], fn ($value) => $value !== null);

        $repeats = $recurrence->label($every);

        return new PreparedAction($payload, sprintf(
            'Pronto: lembrete "%s" para %s às %s%s.',
            $payload['text'],
            $at->format('d/m'),
            $at->format('H:i'),
            $repeats ? ", {$repeats}" : '',
        ));
    }

    /** As regras do formulário de lembrete do app. */
    public function validatePayload(array $payload, ToolContext $context): array
    {
        return RequestRules::validate(StoreReminderRequest::class, $payload);
    }

    public function card(array $payload, ?array $result, ToolContext $context): array
    {
        $at = ReminderService::parseLocal($payload['remind_at'], $context->timezone());

        return [
            'type' => 'reminder_created',
            'reminder' => [
                'id' => $result['reminder_id'] ?? null,
                'text' => $payload['text'],
                // No fuso da pessoa, como nas telas: "2026-10-20T09:00:00-03:00".
                'remind_at' => $at->toIso8601String(),
                'recurrence' => $payload['recurrence'] ?? 'none',
                'every' => $payload['every'] ?? 1,
                'visibility' => $payload['visibility'] ?? 'private',
            ],
        ];
    }

    public function execute(array $payload, ToolContext $context): array
    {
        return ['reminder_id' => $this->reminders->create($context->user, $payload)->id];
    }

    public function undo(array $result, ToolContext $context): void
    {
        Reminder::query()->whereKey($result['reminder_id'] ?? null)->delete();
    }
}
