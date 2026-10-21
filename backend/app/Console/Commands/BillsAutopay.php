<?php

namespace App\Console\Commands;

use App\Alerts\BillAlerts;
use Illuminate\Console\Command;

/**
 * Lança as contas de débito automático no dia do vencimento (no débito) e avisa no Chat, com
 * "Desfazer". Se o valor muda todo mês, só avisa pedindo o valor. Roda de hora em hora.
 *
 *   php artisan bills:autopay
 */
class BillsAutopay extends Command
{
    protected $signature = 'bills:autopay';

    protected $description = 'Lança as contas de débito automático que vencem hoje';

    public function handle(BillAlerts $bills): int
    {
        $this->info('Contas de débito automático tratadas: '.$bills->runAutopay().'.');

        return self::SUCCESS;
    }
}
