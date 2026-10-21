<?php

namespace App\Policies;

use App\Models\RecurringIncome;
use App\Models\User;
use App\Policies\Concerns\ChecksAuthorship;

/** As mesmas regras dos lançamentos: quem cadastrou muda a sua receita fixa; o dono da casa, todas. */
class RecurringIncomePolicy
{
    use ChecksAuthorship;

    public function update(User $user, RecurringIncome $income): bool
    {
        return $this->canChange($user, $income->created_by);
    }

    public function delete(User $user, RecurringIncome $income): bool
    {
        return $this->canChange($user, $income->created_by);
    }
}
