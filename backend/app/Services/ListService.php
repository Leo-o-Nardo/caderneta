<?php

namespace App\Services;

use App\Enums\ListKind;
use App\Enums\Visibility;
use App\Models\HouseholdList;
use App\Models\ListItem;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ListService
{
    public function create(User $author, array $data): HouseholdList
    {
        return DB::transaction(function () use ($author, $data) {
            $list = new HouseholdList(Arr::except($data, 'is_default'));
            $list->icon ??= ListKind::from($data['kind'])->defaultIcon();
            $list->created_by = $author->id;
            $list->position = (int) HouseholdList::query()->max('position') + 1;
            $list->save();

            if (! empty($data['is_default'])) {
                $this->makeDefault($list);
            }

            return $list;
        });
    }

    public function update(HouseholdList $list, array $data): HouseholdList
    {
        return DB::transaction(function () use ($list, $data) {
            $list->fill(Arr::except($data, 'is_default'));

            if ($list->is_default && $list->visibility === Visibility::Private) {
                throw ValidationException::withMessages(['visibility' => __('lists.private_default')]);
            }

            $list->save();

            if (! empty($data['is_default'])) {
                $this->makeDefault($list);
            }

            return $list;
        });
    }

    public function delete(HouseholdList $list): void
    {
        if ($list->is_default) {
            throw ValidationException::withMessages(['list' => __('lists.delete_default')]);
        }

        $list->delete();
    }

    /**
     * Torna esta a lista de "coloca na lista". Ela precisa ser da casa: uma lista privada não
     * apareceria para os outros membros.
     */
    public function makeDefault(HouseholdList $list): void
    {
        if ($list->visibility === Visibility::Private) {
            throw ValidationException::withMessages(['is_default' => __('lists.private_default')]);
        }

        HouseholdList::query()
            ->withoutGlobalScope('visibility')
            ->where('is_default', true)
            ->whereKeyNot($list->id)
            ->update(['is_default' => false]);

        $list->forceFill(['is_default' => true])->save();
    }

    /**
     * Adiciona vários itens de uma vez ("leite, café e sabão em pó"), sem duplicar: o que já está
     * em aberto na lista é ignorado, e o que já foi comprado volta a ficar em aberto. A comparação
     * ignora maiúsculas, acentos e espaços ("Café" = "cafe").
     *
     * @return array{added: Collection<int, ListItem>, existing: Collection<int, ListItem>}
     */
    public function addItems(HouseholdList $list, User $author, array $items): array
    {
        return DB::transaction(function () use ($list, $author, $items) {
            $known = $list->items()->get()->keyBy(fn (ListItem $item) => $this->normalize($item->text));
            $position = (int) $list->items()->max('position');
            $added = collect();
            $existing = collect();

            foreach ($items as $data) {
                $key = $this->normalize($data['text']);
                $item = $known->get($key);

                // Repetido no mesmo pedido ("leite, café, leite"): já foi tratado.
                if ($added->has($key) || $existing->has($key)) {
                    continue;
                }

                if ($item && $item->checked_at === null) {
                    $existing->put($key, $item);

                    continue;
                }

                if ($item) {
                    // Já comprado: volta para o fim da lista, em aberto, com os dados novos.
                    $item->fill(Arr::except($data, 'text'));
                    $item->forceFill(['checked_at' => null, 'checked_by' => null]);
                } else {
                    $item = new ListItem($data);
                    $item->forceFill(['list_id' => $list->id, 'created_by' => $author->id]);
                }

                $item->position = ++$position;
                $item->save();

                $known->put($key, $item);
                $added->put($key, $item);
            }

            return ['added' => $added->values(), 'existing' => $existing->values()];
        });
    }

    public function updateItem(ListItem $item, User $user, array $data): ListItem
    {
        $item->fill(Arr::except($data, 'checked'));

        if (array_key_exists('checked', $data)) {
            $this->setChecked($item, (bool) $data['checked'], $user);
        }

        $item->save();

        return $item;
    }

    /** Marca como comprados os itens em aberto: todos, ou só os ids informados. Devolve quantos. */
    public function check(HouseholdList $list, User $user, ?array $itemIds = null): int
    {
        return $list->items()
            ->whereNull('checked_at')
            ->when($itemIds !== null, fn ($query) => $query->whereKey($itemIds))
            ->update(['checked_at' => now(), 'checked_by' => $user->id]);
    }

    /** Apaga os itens já comprados. Devolve quantos. */
    public function clearChecked(HouseholdList $list): int
    {
        return $list->items()->whereNotNull('checked_at')->delete();
    }

    /** Recebe todos os ids da lista na nova ordem. */
    public function reorder(HouseholdList $list, array $itemIds): void
    {
        DB::transaction(function () use ($list, $itemIds) {
            $current = $list->items()->pluck('id')->sort()->values()->all();
            $sorted = collect($itemIds)->map(fn ($id) => (int) $id)->sort()->values()->all();

            if ($current !== $sorted) {
                throw ValidationException::withMessages(['ids' => __('lists.reorder_mismatch')]);
            }

            foreach (array_values($itemIds) as $position => $id) {
                $list->items()->whereKey($id)->update(['position' => $position]);
            }
        });
    }

    private function setChecked(ListItem $item, bool $checked, User $user): void
    {
        if ($checked && $item->checked_at === null) {
            $item->forceFill(['checked_at' => now(), 'checked_by' => $user->id]);
        } elseif (! $checked) {
            $item->forceFill(['checked_at' => null, 'checked_by' => null]);
        }
    }

    private function normalize(string $text): string
    {
        return Str::lower(Str::ascii(Str::squish($text)));
    }
}
