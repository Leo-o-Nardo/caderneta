<?php

namespace App\Enums;

enum BudgetStatus: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Over = 'over';

    /** A partir de 80% do limite é alerta; a partir de 100%, estourado. */
    public static function fromPercent(int $percent): self
    {
        return match (true) {
            $percent >= 100 => self::Over,
            $percent >= 80 => self::Warning,
            default => self::Ok,
        };
    }
}
