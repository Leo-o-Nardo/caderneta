<?php

namespace App\Services;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HouseholdService
{
    public function createFor(User $owner, string $name): Household
    {
        return DB::transaction(function () use ($owner, $name) {
            $household = Household::create(['name' => $name]);
            $this->addMember($household, $owner, HouseholdRole::Owner);
            DefaultCategories::createFor($household);
            DefaultLists::createFor($household);

            return $household;
        });
    }

    public function createDefaultFor(User $owner): Household
    {
        return $this->createFor($owner, __('households.default_name'));
    }

    public function addMember(Household $household, User $user, HouseholdRole $role): void
    {
        $household->members()->attach($user->id, ['role' => $role->value]);
        $this->switchTo($user, $household);
    }

    public function switchTo(User $user, Household $household): void
    {
        $user->forceFill(['current_household_id' => $household->id])->save();
    }

    /**
     * Devolve a casa atual do usuário com a participação carregada. Se ele não faz mais parte
     * dela, usa a casa mais antiga dele ou cria uma nova: o usuário sempre precisa ter onde
     * registrar as coisas.
     */
    public function ensureCurrent(User $user): Household
    {
        $current = $user->households()->whereKey($user->current_household_id)->first();

        if ($current) {
            return $current;
        }

        $fallback = $user->households()->oldest('household_user.created_at')->first()
            ?? $this->createDefaultFor($user);

        $this->switchTo($user, $fallback);

        return $user->households()->whereKey($fallback->id)->first();
    }

    public function changeRole(Household $household, User $member, HouseholdRole $role): void
    {
        DB::transaction(function () use ($household, $member, $role) {
            $this->lock($household);

            if ($role !== HouseholdRole::Owner) {
                $this->ensureAnotherOwner($household, $member, 'role');
            }

            $household->members()->updateExistingPivot($member->id, ['role' => $role->value]);
        });
    }

    public function removeMember(Household $household, User $member): void
    {
        DB::transaction(function () use ($household, $member) {
            $this->lock($household);
            $this->ensureAnotherOwner($household, $member, 'member');

            $household->members()->detach($member->id);
        });

        $this->ensureCurrent($member);
    }

    public function leave(User $user, Household $household): void
    {
        DB::transaction(function () use ($household, $user) {
            $this->lock($household);
            $this->ensureAnotherOwner($household, $user, 'household');

            $household->members()->detach($user->id);
        });

        $this->ensureCurrent($user);
    }

    public function delete(Household $household): void
    {
        DB::transaction(function () use ($household) {
            $memberIds = $household->members()->pluck('users.id');

            $household->delete();

            User::whereKey($memberIds)->get()->each(fn (User $user) => $this->ensureCurrent($user));
        });
    }

    /**
     * Enfileira as mudanças de membros de uma casa, para que dois donos saindo ao mesmo tempo
     * não a deixem sem dono.
     */
    private function lock(Household $household): void
    {
        Household::whereKey($household->id)->lockForUpdate()->first();
    }

    private function ensureAnotherOwner(Household $household, User $member, string $field): void
    {
        $owners = $household->members()
            ->wherePivot('role', HouseholdRole::Owner->value)
            ->pluck('users.id');

        if ($owners->contains($member->id) && $owners->count() === 1) {
            throw ValidationException::withMessages([$field => __('households.last_owner')]);
        }
    }
}
