<?php

namespace App\Services;

use App\Enums\HouseholdRole;
use App\Models\Card;
use App\Models\Household;
use App\Models\HouseholdInvitation;
use App\Models\Membership;
use App\Models\RecurringBill;
use App\Models\RecurringIncome;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Os primeiros passos de uma pessoa numa casa (guia 1.7). "Feito" sai sempre dos cadastros de
 * verdade: apagar o único cartão volta o passo para "a fazer". Na participação (household_user)
 * fica guardado só o que não dá para calcular: as boas-vindas vistas, os passos ignorados, as
 * categorias conferidas, o bloco escondido do Início e o ponto do tour.
 */
class OnboardingService
{
    // Os passos, na ordem da tela. owner: só o dono vê; skippable: dá para ignorar.
    public const STEPS = [
        'household_name' => ['owner' => true, 'skippable' => false],
        'first_transaction' => ['owner' => false, 'skippable' => false],
        'cards' => ['owner' => false, 'skippable' => true],
        'recurring_income' => ['owner' => false, 'skippable' => true],
        'recurring_bills' => ['owner' => false, 'skippable' => true],
        'categories' => ['owner' => false, 'skippable' => false],
        'invite' => ['owner' => true, 'skippable' => true],
    ];

    /** O estado para o app: os passos já calculados, a contagem e o que foi guardado. */
    public function state(Household $household, User $user, HouseholdRole $role): array
    {
        $saved = $this->membership($household, $user)->onboarding ?? [];
        $skipped = $saved['skipped'] ?? [];

        $steps = $this->stepsFor($role)->map(function (string $key) use ($household, $user, $saved, $skipped) {
            $detail = $this->doneDetail($key, $household, $user, $saved);

            return [
                'key' => $key,
                'status' => $detail !== null ? 'done' : (in_array($key, $skipped, true) ? 'skipped' : 'pending'),
                // O que foi feito ("Nubank e Inter"), para a linha do passo; null se não está feito.
                'detail' => $detail === '' ? null : $detail,
                'skippable' => self::STEPS[$key]['skippable'],
            ];
        })->values();

        return [
            'welcome_seen' => isset($saved['welcome_seen_at']),
            // Quem chega por convite encontra a casa já montada: as boas-vindas ficam mais curtas.
            'joined' => $role !== HouseholdRole::Owner,
            'hidden' => (bool) ($saved['hidden'] ?? false),
            'tour' => [
                'step' => $saved['tour_step'] ?? null,
                'completed' => isset($saved['tour_completed_at']),
            ],
            'steps' => $steps->all(),
            'done' => $steps->where('status', 'done')->count(),
            'skipped' => $steps->where('status', 'skipped')->count(),
            'total' => $steps->count(),
        ];
    }

    /**
     * Guarda as escolhas. A linha da participação fica travada durante a leitura e a escrita: dois
     * toques seguidos (ignorar um passo e esconder o bloco) não apagam um ao outro.
     *
     * @param  array{welcome_seen?: bool, hidden?: bool, skip?: string, unskip?: string, categories_reviewed?: bool, tour_step?: int|null, tour_completed?: bool}  $changes
     */
    public function update(Household $household, User $user, array $changes): void
    {
        DB::transaction(function () use ($household, $user, $changes) {
            $membership = Membership::query()->lockForUpdate()->findOrFail($this->membership($household, $user)->id);
            $saved = $membership->onboarding ?? [];
            $now = now()->toIso8601String();

            if ($changes['welcome_seen'] ?? false) {
                $saved['welcome_seen_at'] ??= $now;
            }

            if (array_key_exists('hidden', $changes)) {
                $saved['hidden'] = (bool) $changes['hidden'];
            }

            if (isset($changes['skip'])) {
                $saved['skipped'] = array_values(array_unique([...($saved['skipped'] ?? []), $changes['skip']]));
            }

            if (isset($changes['unskip'])) {
                $saved['skipped'] = array_values(array_diff($saved['skipped'] ?? [], [$changes['unskip']]));
            }

            if ($changes['categories_reviewed'] ?? false) {
                $saved['categories_reviewed_at'] ??= $now;
            }

            if (array_key_exists('tour_step', $changes)) {
                $saved['tour_step'] = $changes['tour_step'];
            }

            if ($changes['tour_completed'] ?? false) {
                // Terminar zera o ponto: "Fazer o tour" de novo começa do primeiro passo.
                $saved['tour_completed_at'] = $now;
                $saved['tour_step'] = null;
            }

            $membership->forceFill(['onboarding' => $saved])->save();
        });
    }

    /** Os passos que esse papel vê: o "Só leitura" não cadastra nada, então fica só com o tour. */
    public function stepsFor(HouseholdRole $role): Collection
    {
        if (! $role->canWrite()) {
            return collect();
        }

        return collect(self::STEPS)
            ->filter(fn (array $step) => ! $step['owner'] || $role === HouseholdRole::Owner)
            ->keys();
    }

    /** Os passos que esse papel pode ignorar (a validação do PATCH usa). */
    public function skippableFor(HouseholdRole $role): array
    {
        return $this->stepsFor($role)->filter(fn (string $key) => self::STEPS[$key]['skippable'])->values()->all();
    }

    /**
     * O que mostra que o passo está feito ("Nubank e Inter"), ou null se ainda não está. Um texto
     * vazio é feito sem detalhe. As consultas passam pelo filtro da casa atual (BelongsToHousehold).
     */
    private function doneDetail(string $key, Household $household, User $user, array $saved): ?string
    {
        return match ($key) {
            'household_name' => $household->name !== __('households.default_name') ? $household->name : null,
            'first_transaction' => Transaction::query()->where('created_by', $user->id)->latest('id')->value('description'),
            'cards' => $this->names(Card::query()->active()->where('user_id', $user->id)->orderBy('name')->pluck('name')),
            'recurring_income' => $this->names(RecurringIncome::query()->active()->orderBy('description')->pluck('description')),
            'recurring_bills' => $this->names(RecurringBill::query()->active()->orderBy('name')->pluck('name')),
            'categories' => isset($saved['categories_reviewed_at']) ? __('onboarding.categories_reviewed') : null,
            'invite' => $this->inviteDetail($household, $user),
        };
    }

    private function inviteDetail(Household $household, User $user): ?string
    {
        $others = $household->members()->whereKeyNot($user->id)->orderBy('household_user.created_at')->pluck('users.name');

        if ($others->isNotEmpty()) {
            return $this->names($others->map(fn (string $name) => strtok($name, ' ')));
        }

        $pending = HouseholdInvitation::query()->where('household_id', $household->id)->pending()->exists();

        return $pending ? __('onboarding.invitation_open') : null;
    }

    /** "Luz", "Luz e Água" ou "Luz, Água e mais 2". Sem nenhum, null (o passo não está feito). */
    private function names(Collection $names): ?string
    {
        if ($names->isEmpty()) {
            return null;
        }

        if ($names->count() <= 2) {
            return $names->join(' e ');
        }

        return __('onboarding.and_more', ['names' => $names->take(2)->join(', '), 'count' => $names->count() - 2]);
    }

    private function membership(Household $household, User $user): Membership
    {
        return Membership::query()
            ->where('household_id', $household->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }
}
