<?php

namespace App\Models\Concerns;

use App\Models\Household;
use App\Support\HouseholdContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Liga o modelo à casa do HouseholdContext: as consultas só enxergam as linhas daquela casa, e
 * as linhas novas recebem o household_id dela. Fora de uma requisição (seeders, comandos) nada
 * é filtrado até alguém preencher o contexto.
 */
trait BelongsToHousehold
{
    public static function bootBelongsToHousehold(): void
    {
        static::addGlobalScope('household', function (Builder $query) {
            $context = app(HouseholdContext::class);

            if ($context->has()) {
                $query->where($query->qualifyColumn('household_id'), $context->household()->id);
            }
        });

        static::creating(function (Model $model) {
            $context = app(HouseholdContext::class);

            if (! $model->household_id && $context->has()) {
                $model->household_id = $context->household()->id;
            }
        });
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }
}
