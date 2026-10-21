<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class Month
{
    /** "2026-10" vira o dia 1º desse mês; sem valor, o mês atual no fuso informado. */
    public static function parse(?string $value, string $timezone): CarbonImmutable
    {
        return $value
            ? CarbonImmutable::createFromFormat('!Y-m', $value, $timezone)
            : CarbonImmutable::now($timezone)->startOfMonth();
    }

    /**
     * O dia $day no mês de $month. Dias que não existem no mês caem no último dia dele:
     * dia 31 em fevereiro vira 28 (ou 29).
     */
    public static function day(CarbonImmutable $month, int $day): CarbonImmutable
    {
        return $month->startOfMonth()->day(min($day, $month->daysInMonth));
    }
}
