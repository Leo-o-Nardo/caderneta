<?php

namespace App\Models\Concerns;

use App\Enums\Visibility;
use App\Support\HouseholdContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Esconde os registros privados de quem não os criou. Funciona como o BelongsToHousehold: o
 * filtro vale para toda consulta, então nenhum total ou lista mostra o privado de outra pessoa.
 * Sem usuário no contexto (um job da casa inteira, por exemplo), só os compartilhados aparecem.
 */
trait HasVisibility
{
    public static function bootHasVisibility(): void
    {
        static::addGlobalScope('visibility', function (Builder $query) {
            $context = app(HouseholdContext::class);

            if (! $context->has()) {
                return;
            }

            $userId = $context->user()?->id;

            $query->where(function (Builder $inner) use ($query, $userId) {
                $inner->where($query->qualifyColumn('visibility'), Visibility::Household->value);

                if ($userId) {
                    $inner->orWhere($query->qualifyColumn('created_by'), $userId);
                }
            });
        });
    }
}
