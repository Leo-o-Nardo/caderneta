<?php

namespace App\Services;

use App\Enums\ListKind;
use App\Models\Household;
use App\Models\HouseholdList;

/** As listas com que toda casa nova começa. A de mercado é a padrão. */
final class DefaultLists
{
    private const LISTS = [
        ['Mercado', ListKind::Shopping],
        ['Desejos', ListKind::Wishlist],
        ['Casa', ListKind::Todo],
    ];

    public static function createFor(Household $household): void
    {
        foreach (self::LISTS as $position => [$name, $kind]) {
            $list = new HouseholdList([
                'name' => $name,
                'kind' => $kind,
                'icon' => $kind->defaultIcon(),
            ]);

            $list->forceFill([
                'household_id' => $household->id,
                'position' => $position,
                'is_default' => $position === 0,
            ])->save();
        }
    }
}
