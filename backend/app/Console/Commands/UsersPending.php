<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Os cadastros esperando liberação (o plano B quando o e-mail não chegou ou o link venceu).
 *
 *   php artisan users:pending
 */
class UsersPending extends Command
{
    protected $signature = 'users:pending';

    protected $description = 'Lista os cadastros que esperam liberação';

    public function handle(): int
    {
        $users = User::query()->whereNull('approved_at')->whereNull('rejected_at')->orderBy('created_at')->get();

        if ($users->isEmpty()) {
            $this->info('Nenhum cadastro esperando liberação.');

            return self::SUCCESS;
        }

        $this->table(
            ['Nome', 'E-mail', 'Cadastro em'],
            $users->map(fn (User $user) => [
                $user->name,
                $user->email,
                $user->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            ])->all(),
        );

        $this->line('Para decidir: php artisan users:approve email@exemplo.com (ou users:reject).');

        return self::SUCCESS;
    }
}
