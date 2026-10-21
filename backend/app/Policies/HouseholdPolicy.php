<?php

namespace App\Policies;

use App\Models\Household;
use App\Models\User;

class HouseholdPolicy
{
    public function view(User $user, Household $household): bool
    {
        return $user->roleIn($household) !== null;
    }

    /** Criar e alterar os registros da casa (lançamentos, listas, contas...). */
    public function write(User $user, Household $household): bool
    {
        return $user->roleIn($household)?->canWrite() ?? false;
    }

    public function update(User $user, Household $household): bool
    {
        return $user->roleIn($household)?->canManageMembers() ?? false;
    }

    public function manageMembers(User $user, Household $household): bool
    {
        return $user->roleIn($household)?->canManageMembers() ?? false;
    }

    public function delete(User $user, Household $household): bool
    {
        return $user->roleIn($household)?->canManageMembers() ?? false;
    }
}
