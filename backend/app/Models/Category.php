<?php

namespace App\Models;

use App\Enums\CategoryKind;
use App\Models\Concerns\BelongsToHousehold;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Fillable(['kind', 'name', 'icon', 'color'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use BelongsToHousehold, HasFactory;

    /**
     * Os ícones que uma categoria pode ter, na ordem em que o app mostra. São chaves: o app traduz
     * cada uma para o desenho (CategoryIcon), e o assistente escolhe entre elas ao criar categorias.
     */
    public const ICONS = [
        'cart', 'utensils', 'coffee', 'bike', 'car', 'fuel', 'home', 'bolt',
        'droplet', 'phone', 'heart', 'pill', 'ticket', 'gamepad', 'plane', 'bag',
        'shirt', 'scissors', 'repeat', 'book', 'baby', 'paw', 'gift', 'dumbbell',
        'wrench', 'shield', 'landmark', 'wallet', 'briefcase', 'trending-up', 'piggy-bank', 'dots',
    ];

    protected function casts(): array
    {
        return [
            'kind' => CategoryKind::class,
            'position' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Uma categoria nova entra no fim do tipo dela. Roda depois do BelongsToHousehold (que preenche
     * o household_id) e conta também as arquivadas, para nunca repetir uma posição.
     */
    protected static function booted(): void
    {
        static::creating(function (Category $category) {
            if ($category->position !== null) {
                return;
            }

            $last = static::withoutGlobalScopes()
                ->where('household_id', $category->household_id)
                ->where('kind', $category->kind)
                ->max('position');

            $category->position = $last === null ? 0 : $last + 1;
        });
    }

    /**
     * Grava a nova ordem de um tipo. Os ids precisam ser exatamente as categorias ativas desse tipo,
     * cada uma uma vez: uma lista incompleta deixaria posições repetidas.
     *
     * @param  list<int>  $ids
     */
    public static function reorder(CategoryKind $kind, array $ids): void
    {
        DB::transaction(function () use ($kind, $ids) {
            $current = static::query()->active()->where('kind', $kind)->pluck('id')->sort()->values()->all();
            $sorted = collect($ids)->map(fn ($id) => (int) $id)->sort()->values()->all();

            if ($current !== $sorted) {
                throw ValidationException::withMessages(['ids' => __('finance.reorder_mismatch')]);
            }

            foreach (array_values($ids) as $position => $id) {
                static::query()->whereKey($id)->update(['position' => $position]);
            }
        });
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function budget(): HasOne
    {
        return $this->hasOne(Budget::class);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /** Se já existe uma categoria ativa deste tipo com esse nome, sem diferenciar maiúsculas. */
    public static function nameTaken(CategoryKind $kind, string $name, ?int $ignoreId = null): bool
    {
        return static::query()
            ->active()
            ->where('kind', $kind)
            ->whereRaw('lower(name) = ?', [mb_strtolower(trim($name))])
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
