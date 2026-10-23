<?php

namespace App\Console\Commands;

use App\Alerts\ExpoPush;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Manda uma notificação de teste para os celulares de alguém, sem passar pelo Chat. Serve para
 * conferir o push sem esperar uma conta vencer ou um lembrete chegar.
 *
 *   php artisan alerts:test-push leo@exemplo.com
 */
class AlertTestPush extends Command
{
    protected $signature = 'alerts:test-push {email : O e-mail da conta}';

    protected $description = 'Manda uma notificação de teste para os celulares de uma conta';

    public function handle(ExpoPush $push): int
    {
        $user = User::query()->where('email', mb_strtolower(trim($this->argument('email'))))->first();

        if ($user === null) {
            $this->error('Nenhuma conta com esse e-mail.');

            return self::FAILURE;
        }

        $tokens = $user->pushTokens()->pluck('token')->all();

        if ($tokens === []) {
            $this->warn("{$user->name} não tem nenhum celular registrado: entre no app e aceite as notificações.");

            return self::FAILURE;
        }

        $accepted = $push->send($tokens, __('alerts.push_titles.test'), __('alerts.push_test'), ['kind' => 'test']);
        $this->info("A Expo aceitou {$accepted} de ".count($tokens).' notificação(ões).');

        return $accepted > 0 ? self::SUCCESS : self::FAILURE;
    }
}
