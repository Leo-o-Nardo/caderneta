<?php

namespace App\Console\Commands;

use App\Alerts\WeeklySummary;
use Illuminate\Console\Command;

/**
 * Manda no Chat o resumo da semana: domingo, a partir das 19h no fuso de cada pessoa. Roda de
 * hora em hora; o mesmo resumo não sai duas vezes.
 *
 *   php artisan alerts:weekly
 */
class AlertWeekly extends Command
{
    protected $signature = 'alerts:weekly';

    protected $description = 'Manda no Chat o resumo da semana (domingo, às 19h)';

    public function handle(WeeklySummary $summary): int
    {
        $this->info('Resumos da semana: '.$summary->sendDue().'.');

        return self::SUCCESS;
    }
}
