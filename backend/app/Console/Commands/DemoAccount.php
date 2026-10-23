<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccountService;
use Database\Seeders\DemoHouseholdSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * A conta de demonstração para a revisão das lojas: já liberada, com a casa de exemplo de 3 meses
 * (DemoHouseholdSeeder) dividida com a Ana. Rodar de novo refaz tudo do zero, com as datas de hoje.
 *
 *   php artisan demo:account revisao@exemplo.com --password=UmaSenha123
 *
 * Com a mesma senha, os dados que estão nas fichas das lojas continuam valendo. Sem ela, uma nova é
 * sorteada e mostrada no fim. A Ana tem uma senha sorteada que ninguém vê: ela só dá vida à casa.
 */
class DemoAccount extends Command
{
    private const PARTNER_EMAIL = 'ana.demo@example.com';

    protected $signature = 'demo:account
        {email : O e-mail da conta de revisão}
        {--password= : A senha (sem ela, uma nova é sorteada)}';

    protected $description = 'Cria (ou refaz do zero) a conta de demonstração para a revisão das lojas';

    public function handle(AccountService $accounts): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        $password = $this->option('password') ?: Str::password(12, symbols: false);

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => [Password::defaults()]],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        // A Ana sai primeiro: assim a casa antiga fica só com o dono e é apagada junto com ele.
        foreach ([self::PARTNER_EMAIL, $email] as $old) {
            if ($user = User::query()->where('email', $old)->first()) {
                $accounts->delete($user);
            }
        }

        $seeder = app(DemoHouseholdSeeder::class);
        $seeder->owner = ['email' => $email, 'password' => $password];
        $seeder->partner = ['email' => self::PARTNER_EMAIL, 'password' => Str::password(32)];
        $seeder->run();

        $this->info('Conta de demonstração pronta, com a casa de exemplo.');
        $this->table(['E-mail', 'Senha'], [[$email, $password]]);
        $this->line('Use estes dados no acesso para a revisão do Play Console e do App Store Connect.');

        return self::SUCCESS;
    }
}
