<?php

namespace App\Services;

use App\Enums\HouseholdRole;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    public const EXPIRES_DAYS = 7;

    public const MAX_PENDING = 10;

    public function __construct(private readonly HouseholdService $households) {}

    public function create(Household $household, User $inviter, HouseholdRole $role): HouseholdInvitation
    {
        if ($household->invitations()->pending()->count() >= self::MAX_PENDING) {
            throw ValidationException::withMessages(['role' => __('households.too_many_invitations')]);
        }

        return $household->invitations()->create([
            'code' => HouseholdInvitation::generateCode(),
            'role' => $role,
            'invited_by' => $inviter->id,
            'expires_at' => now()->addDays(self::EXPIRES_DAYS),
        ]);
    }

    /** O convite deste código, ou null se ele não existe ou não pode mais ser usado. */
    public function find(string $code): ?HouseholdInvitation
    {
        $invitation = HouseholdInvitation::where('code', Str::upper($code))->first();

        return $invitation?->isUsable() ? $invitation : null;
    }

    public function accept(string $code, User $user): Household
    {
        return DB::transaction(function () use ($code, $user) {
            // A trava na linha faz dois aceites simultâneos do mesmo código rodarem um depois do outro.
            $invitation = HouseholdInvitation::where('code', Str::upper($code))->lockForUpdate()->first();

            if (! $invitation?->isUsable()) {
                throw ValidationException::withMessages(['code' => __('households.invitation_invalid')]);
            }

            if ($user->roleIn($invitation->household)) {
                throw ValidationException::withMessages(['code' => __('households.already_member')]);
            }

            $this->households->addMember($invitation->household, $user, $invitation->role);
            $invitation->forceFill(['accepted_at' => now(), 'accepted_by' => $user->id])->save();

            return $invitation->household;
        });
    }

    public function revoke(HouseholdInvitation $invitation): void
    {
        $invitation->forceFill(['revoked_at' => now()])->save();
    }
}
