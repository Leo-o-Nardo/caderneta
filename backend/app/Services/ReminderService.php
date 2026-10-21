<?php

namespace App\Services;

use App\Models\Reminder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

/**
 * Lembretes. O app manda horários locais ("2026-10-20 09:00", ou só "2026-10-20", que vira 9h)
 * e eles são gravados em UTC. As repetições são calculadas no fuso do usuário.
 */
class ReminderService
{
    public const DEFAULT_HOUR = 9;

    public function create(User $author, array $data): Reminder
    {
        $at = self::parseLocal($data['remind_at'], $author->timezone);

        $reminder = new Reminder(Arr::except($data, 'remind_at'));
        $reminder->forceFill([
            'created_by' => $author->id,
            'remind_at' => $at->utc(),
            'anchor_at' => $at->utc(),
        ])->save();

        return $reminder;
    }

    public function update(Reminder $reminder, User $user, array $data): Reminder
    {
        $reminder->fill(Arr::except($data, 'remind_at'));

        if (isset($data['remind_at'])) {
            // Mudar a data recomeça a série a partir dela.
            $at = self::parseLocal($data['remind_at'], $user->timezone)->utc();
            $reminder->forceFill(['remind_at' => $at, 'anchor_at' => $at, 'done_at' => null, 'last_notified_at' => null]);
        }

        $reminder->save();

        return $reminder;
    }

    /**
     * "Feito". Sem repetição, o lembrete é concluído. Com repetição, ele pula para a próxima data
     * da série depois de agora e da data atual (feito antes da hora não repete o mesmo aviso).
     */
    public function done(Reminder $reminder, User $user): Reminder
    {
        $timezone = $user->timezone;
        $now = CarbonImmutable::now($timezone);
        $after = $reminder->remind_at->setTimezone($timezone)->max($now);

        $next = $reminder->recurrence->nextAfter($reminder->anchor_at->setTimezone($timezone), $reminder->every, $after);

        $reminder->forceFill($next
            ? ['remind_at' => $next->utc(), 'last_notified_at' => null]
            : ['done_at' => $now->utc()]
        )->save();

        return $reminder;
    }

    /** "Me lembra depois": muda só o próximo aviso. Sem data, adia 24 horas. */
    public function snooze(Reminder $reminder, User $user, ?string $until): Reminder
    {
        $at = $until
            ? self::parseLocal($until, $user->timezone)
            : CarbonImmutable::now($user->timezone)->addDay();

        $reminder->forceFill(['remind_at' => $at->utc(), 'last_notified_at' => null])->save();

        return $reminder;
    }

    /** "2026-10-20 09:00" ou "2026-10-20" (9h), no fuso informado. */
    public static function parseLocal(string $value, string $timezone): CarbonImmutable
    {
        if (strlen($value) === 10) {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone)->setTime(self::DEFAULT_HOUR, 0);
        }

        return CarbonImmutable::createFromFormat('!Y-m-d H:i', $value, $timezone);
    }
}
