<?php

namespace App\Console\Commands;

use App\Alerts\BillAlerts;
use Illuminate\Console\Command;

/**
 * Avisa no Chat as contas que vencem amanhã (véspera) e as que vencem hoje e seguem em aberto, a
 * partir das 9h no fuso de cada pessoa. Roda de hora em hora; o mesmo aviso não sai duas vezes.
 *
 *   php artisan alerts:bills
 */
class AlertBills extends Command
{
    protected $signature = 'alerts:bills';

    protected $description = 'Avisa no Chat as contas que vencem amanhã ou hoje';

    public function handle(BillAlerts $bills): int
    {
        $this->info('Avisos de conta: '.$bills->sendDue().'.');

        return self::SUCCESS;
    }
}
