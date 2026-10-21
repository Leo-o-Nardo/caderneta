<?php

namespace App\Policies;

use App\Models\InstallmentPlan;
use App\Models\User;
use App\Policies\Concerns\ChecksAuthorship;

class InstallmentPlanPolicy
{
    use ChecksAuthorship;

    public function update(User $user, InstallmentPlan $plan): bool
    {
        return $this->canChange($user, $plan->created_by);
    }

    public function delete(User $user, InstallmentPlan $plan): bool
    {
        return $this->canChange($user, $plan->created_by);
    }
}
