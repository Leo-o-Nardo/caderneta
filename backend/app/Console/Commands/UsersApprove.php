<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccessService;
use Illuminate\Console\Command;

/**
 * Libera o acesso de alguém pelo terminal, como o botão do e-mail. A pessoa recebe o aviso.
 *
 *   php artisan users:approve ana@exemplo.com
 */
class UsersApprove extends Command
{
    protected $signature = 'users:approve {email : O e-mail da conta}';

    protected $description = 'Libera o acesso de um cadastro';

    public function handle(AccessService $access): int
    {
        $user = User::query()->where('email', mb_strtolower(trim($this->argument('email'))))->first();

        if ($user === null) {
            $this->error('Nenhuma conta com esse e-mail.');

            return self::FAILURE;
        }

        $access->approve($user);
        $this->info("Acesso liberado: {$user->name} ({$user->email}).");

        return self::SUCCESS;
    }
}
