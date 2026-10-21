<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccessService;
use Illuminate\Console\Command;

/**
 * Recusa um cadastro pelo terminal, como o botão do e-mail: a pessoa recebe um aviso educado e,
 * se já tinha entrado, sai de todos os aparelhos.
 *
 *   php artisan users:reject alguem@exemplo.com
 */
class UsersReject extends Command
{
    protected $signature = 'users:reject {email : O e-mail da conta}';

    protected $description = 'Recusa um cadastro';

    public function handle(AccessService $access): int
    {
        $user = User::query()->where('email', mb_strtolower(trim($this->argument('email'))))->first();

        if ($user === null) {
            $this->error('Nenhuma conta com esse e-mail.');

            return self::FAILURE;
        }

        $access->reject($user);
        $this->info("Cadastro recusado: {$user->name} ({$user->email}).");

        return self::SUCCESS;
    }
}
