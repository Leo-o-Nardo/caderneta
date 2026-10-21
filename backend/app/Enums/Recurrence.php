<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/** A repetição de um lembrete, combinada com um intervalo ("a cada 6 meses" = Monthly com intervalo 6). */
enum Recurrence: string
{
    case None = 'none';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    /**
     * A primeira data da série depois de $after, ou null quando não se repete.
     *
     * A série é contada a partir da âncora (a data original), e não da última data, para o dia
     * não "escorregar": um lembrete mensal do dia 31 cai em 28/02 e depois volta para 31/03.
     */
    public function nextAfter(CarbonImmutable $anchor, int $every, CarbonImmutable $after): ?CarbonImmutable
    {
        if ($this === self::None) {
            return null;
        }

        for ($step = 1; ; $step++) {
            $candidate = $this->shift($anchor, $step * $every);

            if ($candidate->greaterThan($after)) {
                return $candidate;
            }
        }
    }

    /** "todo mês", "toda semana", "a cada 6 meses"; null quando não repete. */
    public function label(int $every = 1): ?string
    {
        [$one, $many] = match ($this) {
            self::None => [null, null],
            self::Daily => ['dia', 'dias'],
            self::Weekly => ['semana', 'semanas'],
            self::Monthly => ['mês', 'meses'],
            self::Yearly => ['ano', 'anos'],
        };

        if ($one === null) {
            return null;
        }

        if ($every > 1) {
            return "a cada {$every} {$many}";
        }

        return $this === self::Weekly ? "toda {$one}" : "todo {$one}";
    }

    private function shift(CarbonImmutable $date, int $amount): CarbonImmutable
    {
        return match ($this) {
            self::Daily => $date->addDays($amount),
            self::Weekly => $date->addWeeks($amount),
            // "NoOverflow": 31/01 + 1 mês = 28/02, e não 03/03.
            self::Monthly => $date->addMonthsNoOverflow($amount),
            self::Yearly => $date->addYearsNoOverflow($amount),
        };
    }
}
