<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use App\Policies\Concerns\ChecksAuthorship;

class TransactionPolicy
{
    use ChecksAuthorship;

    public function update(User $user, Transaction $transaction): bool
    {
        return $this->canChange($user, $transaction->created_by);
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $this->canChange($user, $transaction->created_by);
    }
}
