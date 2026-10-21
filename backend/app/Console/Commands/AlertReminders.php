<?php

namespace App\Console\Commands;

use App\Alerts\ReminderAlerts;
use Illuminate\Console\Command;

/**
 * Avisa no Chat os lembretes que chegaram na hora. Roda a cada minuto (routes/console.php).
 *
 *   php artisan alerts:reminders
 */
class AlertReminders extends Command
{
    protected $signature = 'alerts:reminders';

    protected $description = 'Avisa no Chat os lembretes que chegaram na hora';

    public function handle(ReminderAlerts $reminders): int
    {
        $this->info('Avisos de lembrete: '.$reminders->sendDue().'.');

        return self::SUCCESS;
    }
}
