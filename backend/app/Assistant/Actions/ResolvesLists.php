<?php

namespace App\Assistant\Actions;

use App\Assistant\NameMatcher;
use App\Assistant\Tools\ToolResult;
use App\Models\HouseholdList;

/** Acha a lista de que a pessoa fala. As listas privadas de outras pessoas nem entram na busca. */
trait ResolvesLists
{
    /** A lista citada pelo nome; sem nome, a padrão da casa ("coloca leite na lista"). */
    protected function findList(?string $name): HouseholdList|ToolResult
    {
        $lists = HouseholdList::query()->orderBy('position')->orderBy('id')->get();

        if ($lists->isEmpty()) {
            return ToolResult::error('A casa ainda não tem listas.');
        }

        if ($name === null) {
            return $lists->firstWhere('is_default', true) ?? $lists->first();
        }

        return NameMatcher::find($lists, $name, fn (HouseholdList $list) => $list->name)
            ?? ToolResult::error(sprintf('Não encontrei a lista "%s". As listas são: %s.', $name, $lists->pluck('name')->join(', ')));
    }

    /** O pedaço do card que diz qual lista mudou. */
    protected static function listCard(?HouseholdList $list): ?array
    {
        return $list ? ['id' => $list->id, 'name' => $list->name, 'kind' => $list->kind->value] : null;
    }
}
