<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Regras da fatura do cartão. Uma compra feita antes do dia de fechamento entra na fatura que
 * fecha naquele mês; no dia do fechamento ou depois, na seguinte. Dias que não existem no mês
 * (31 em fevereiro) caem no último dia do mês.
 */
final class InvoiceCalendar
{
    public static function for(int $closingDay, int $dueDay, CarbonInterface $purchasedOn): InvoicePeriod
    {
        $date = CarbonImmutable::instance($purchasedOn)->startOfDay();
        $closesOn = Month::day($date, $closingDay);

        if ($date->greaterThanOrEqualTo($closesOn)) {
            $closesOn = Month::day($date->startOfMonth()->addMonth(), $closingDay);
        }

        return self::forMonth($closingDay, $dueDay, $closesOn);
    }

    /** A fatura que fecha no mês de $month. */
    public static function forMonth(int $closingDay, int $dueDay, CarbonInterface $month): InvoicePeriod
    {
        $month = CarbonImmutable::instance($month)->startOfMonth();

        // Vence depois do fechamento: mesmo mês (fecha 3, vence 10). Senão, no mês seguinte (fecha 25, vence 5).
        $dueMonth = $dueDay > $closingDay ? $month : $month->addMonth();

        return new InvoicePeriod(
            month: $month,
            closesOn: Month::day($month, $closingDay),
            dueOn: Month::day($dueMonth, $dueDay),
        );
    }
}
